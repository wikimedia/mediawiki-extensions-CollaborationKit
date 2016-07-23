<?php

/**
 * Important design assumption: This class assumes lists are small
 * (e.g. Average case < 500 items, outliers < 2000)
 *
 * Json structure is as follows:
 * {
 * 	items: [
 *		{
 *			"title": "The title, possibly a page name",
 *			"link": "Page to link to if not title, or false to disable link",
 *			"notes": "Freeform wikitext",
 *			"image": "Image to use. Set to false to disable using PageImages ext",
 *			"sortkey": { "name of sort criteria": "sortkey", ... }
 *			"tags": [ "tag1", "tag2", ... ]
 *		},
 *		...
 *	],
 *	"options": {
 *		"sortcriteria": { //unimplemented
 *			// FIXME, not sure if this actually meets with usecase
 *			"criteria name": {
 *				"order": "numeric", // or potentially collation??
 *				"default": "string-Text here" // or "column-title", etc
 *			},
 *			...
 *		},
 *		"defaultsort": "sort-criteria name" // or "column-title" ?? what about multi-key sort??
 *	}
 *	"description": "Some arbitrary wikitext"
 *}
 *
 */
class CollaborationListContent extends JsonContent {

	const MAX_LIST_SIZE = 2000; // Maybe should be a config option.
	const RANDOM_CACHE_EXPIRY = 28800; // 8 hours
	const MAX_TAGS = 50;
	const HUMAN_DESC_SPLIT = "\n-----------------------\n";

	/** @var $decoded boolean Have we decoded the data yet */
	private $decoded = false;
	/** @var $description String Descripton wikitext */
	protected $description;
	/** @var $options StdClass Options for page */
	protected $options;
	/** @var $items Array List of items */
	protected $items;

	function __construct( $text, $type = 'CollaborationListContent' ) {
		parent::__construct( $text, $type );
	}

	/**
	 * Decode and validate the contents.
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		if ( !parent::isValid() ) {
			return false;
		}

		$status = $this->getData();
		if ( !is_object( $status ) || !$status->isOk() ) {
			return false;
		}

		$data = $status->value;

		if (
			!property_exists( $data, "items" )
			|| !property_exists( $data, "options" )
			|| !property_exists( $data, "description" )
		) {
			return false;
		}
		foreach ( $data as $field => $value ) {
			switch ( $field ) {
			case 'items':
				if ( !is_array( $value ) ) {
					return false;
				}
				if ( !$this->validateItems( $value ) ) {
					return false;
				}
				break;
			case 'options':
				if ( !is_object( $value ) ) {
					return false;
				}
				if ( !$this->validateOptions( $value ) ) {
					return false;
				}
				break;
			case 'description':
				if ( !is_string( $value ) ) {
					return false;
				}
				break;
			default:
				return false;
			}
		}
		return true;
	}

	/**
	 * Format json
	 *
	 * Do not escape < and > its unnecessary and ugly
	 * @return string
	 */
	public function beautifyJSON() {
		return FormatJson::encode( $this->getData()->getValue(), true, FormatJson::ALL_OK );
	}

	/**
	* Beautifies JSON and does subst: prior to save.
	*
	* @param Title $title Title
	* @param User $user User
	* @param ParserOptions $popts
	* @return CollaborationListContent
	*/
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		global $wgParser;
		// WikiPage::doEditContent invokes PST before validation. As such, native data
		// may be invalid (though PST result is discarded later in that case).
		$text = $this->getNativeData();
		// pst will hopefully not make json invalid. Def should not.
		$pst = $wgParser->preSaveTransform( $text, $title, $user, $popts );
		$pstContent = new static( $pst );

		if ( !$pstContent->isValid() ) {
			return $this;
		}

		return new static( $pstContent->beautifyJSON() );
	}

	/**
	 * Validate the item structure.
	 *
	 * Format is a list of:
	 *	{
	 *		"title": "The title, possibly but not neccessarily a page name",
	 *		"link": "Page to link to if not title, or false to disable link",
	 *		"notes": "Freeform wikitext",
	 *		"image": "Image to use. Set to false to disable using PageImages ext",
	 *		"sortkey": { "name of sort criteria": "sortkey", ... }
	 *		"tags": [ "tag1", "tag2", ... ]
	 *	}
	 * @param $items Array
	 * @return boolean
	 */
	private function validateItems( array $items ) {
		if ( count( $items ) > self::MAX_LIST_SIZE ) {
			return false;
		}
		$itemsSoFar = [];
		foreach ( $items as $item ) {
			// Fixme do we enforce uniqueness on title?
			if ( !property_exists( $item, 'title' )
				|| !is_string( $item->title )
				|| isset( $itemsSoFar[$item->title] )
			) {
				return false;
			}
			$itemsSoFar[$item->title] = true;
			foreach ( $item as $field => $value ) {
				switch ( $field ) {
				case 'title':
				case 'notes':
					if ( !is_string( $value ) ) {
						return false;
					}
					break;
				case 'link':
				case 'image':
					if ( $value === false ) {
						break;
					}
					if ( !is_string( $value ) ||
						!Title::makeTitleSafe( NS_MAIN, $value )
					) {
						return false;
					}
					break;
				case 'sortkey':
					// FIXME: Validate it matches options.
					if ( !is_object( $sortkey ) ) {
						return false;
					}
					foreach ( $value as $keyname => $keytext ) {
						if ( !is_string( $keytext ) ) {
							return false;
						}
					}
					break;
				case 'tags':
					if ( !is_array( $value ) ) {
						return false;
					}
					foreach ( $value as $tag ) {
						if ( !is_string( $tag ) ) {
							return false;
						}
						if ( !Title::makeTitleSafe( 0, $tag ) ) {
							// Title is not really exactly
							// what we need. It bans some
							// things we don't care about,
							// like "..". But all in all,
							// its a good proxy for a sane
							// tag name.
							return false;
						}
					}
					break;
				default:
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Validate the options structure
	 *
	 * @param $options stdClass
	 * @return boolean
	 * @todo FIXME implement
	 */
	private function validateOptions( stdClass $options ) {
		return true;
	}

	private static function validateOption( $name, &$value ) {
		switch ( $name ) {
		case 'defaultSort':
			if ( in_array( $value, [ 'natural', 'random' ] ) ) {
				return true;
			}
		case 'maxItems':
		case 'offset':
			if ( is_numeric( $value ) ) {
				$value = (int)$value;
				return true;
			}
		case 'includeDesc':
			$value = (bool)$value;
			return true;
		default:
			return false;
		}
	}

	/**
	 * Decode the JSON contents and populate protected variables.
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return;
		}
		if ( !$this->isValid() ) {
			throw new Exception( "Can't decode invalid content" );
		}
		$data = $this->getData()->value;
		$this->description = $data->description;
		$this->options = $data->options;
		$this->items = $data->items;
		$this->decoded = true;
	}

	// Some placeholder getters; add the rest if there's ever any reason to

	/**
	 * @return string
	 */
	public function getDescription() {
		$this->decode();
		return $this->description;
	}

	/**
	 * Fill $output with information derived from the content.
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput $output
	 */
	protected function fillParserOutput( Title $title, $revId, ParserOptions $options,
		$generateHtml, ParserOutput &$output
	) {
		global $wgParser;
		$this->decode();

		$lang = $options->getTargetLanguage();
		if ( !$lang ) {
			$lang = $title->getPageLanguage();
		}
		$text = $this->convertToWikitext( $lang, $this->getFullRenderListOptions() );
		$output = $wgParser->parse( $text, $title, $options, true, true, $revId );
	}

	private function getFullRenderListOptions() {
		return $listOptions = [
			'includeDesc' => true,
			'maxItems' => false,
			'defaultSort' => 'natural'
		];
	}

	/**
	 * Convert the JSON to wikitext.
	 *
	 * @param $lang Language The (content) language to render the page in.
	 * @param $options Array Options to override the default transclude options
	 * @return string The wikitext
	 */
	private function convertToWikitext( Language $lang, $options = [] ) {
		$this->decode();
		$options = $options + $this->getDefaultOptions();
		$maxItems = $options['maxItems'];
		$includeDesc = $options['includeDesc'];

		// Hack to force style loading even when we don't have a Parser reference.
		$text = "<collaborationkitloadliststyles/>\n";

		if ( $includeDesc ) {
			$text .= $this->getDescription() . "\n";
		}
		if ( count( $this->items ) === 0 ) {
			$text .= "<hr>\n{{mediawiki:collaborationkit-listempty}}\n";
			return $text;
		}
		$curItem = 0;
		$offset = $options['defaultSort'] === 'random' ? 0 : $options['offset'];

		$sortedItems = $this->items;
		$this->sortList( $sortedItems, $options['defaultSort'] );
		$text .= '<div class="mw-collabkit-list">' . "\n";

		foreach ( $sortedItems as $item ) {
			if ( $offset !== 0 ) {
				$offset--;
				continue;
			}
			$curItem++;
			if ( $maxItems !== false && $maxItems < $curItem ) {
				break;
			}

			$itemTags = isset( $item->tags ) ? $item->tags : [];
			if ( !$this->matchesTag( $options['tags'], $itemTags ) ) {
				continue;
			}

			$titleForItem = null;
			if ( !isset( $item->link ) ) {
				$titleForItem = Title::newFromText( $item->title );
			} elseif ( $item->link !== false ) {
				$titleForItem = Title::newFromText( $item->link );
			}
			$text .= Html::openElement( 'div', [
				"class" => "mw-collabkit-list-item",
				"data-collabkit-item-title" => $item->title
			] );

			$image = null;
			if ( !isset( $item->image ) && $titleForItem ) {
				if ( class_exists( 'PageImages' ) ) {
					$image = PageImages::getPageImage( $titleForItem );
				}
			} elseif ( is_string( $item->image ) ) {
				$imageTitle = Title::newFromText( $item->image, NS_FILE );
				if ( $imageTitle ) {
					$image = wfFindFile( $imageTitle );
				}
			}

			$text .= '<div class="mw-collabkit-list-img">';
			if ( $image ) {
				// Important: If you change the width of the image
				// you also need to change it in the stylesheet.
				$text .= '[[File:' . $image->getName() . "|left|64px|alt=]]\n";
			} else {
				$text .= '<div class="mw-ckicon-page-grey2 mw-collabkit-list-noimageplaceholder"></div>';
			}
			$text .= '</div>';

			$text .= '<div class="mw-collabkit-list-container">';
			// Question: Arguably it would be more semantically correct to use
			// an <Hn> element for this. Would that be better? Unclear.
			$text .= '<div class="mw-collabkit-list-title">';
			if ( $titleForItem ) {
				$text .= "[[:" . $titleForItem->getPrefixedDBkey() . "|"
					. wfEscapeWikiText( $item->title ) . "]]";
			} else {
				$text .=  wfEscapeWikiText( $item->title );
			}
			$text .= "</div>\n";
			$text .= '<div class="mw-collabkit-list-notes">' . "\n";
			if ( isset( $item->notes ) && is_string( $item->notes ) ) {
				$text .= $item->notes . "\n";
			}

			if ( isset( $item->tags ) && is_array( $item->tags ) && count( $item->tags ) ) {
				$text .= "\n<div class='toccolours mw-collabkit-list-tags'>" .
					wfMessage( 'collaborationkit-taglist' )
						->inLanguage( $lang )
						->params(
							$lang->commaList(
								array_map( 'wfEscapeWikiText', $item->tags )
							)
						)->numParams( count( $item->tags ) )
						->text() .
					"</div>\n";
			}
			$text .= '</div></div></div>' . "\n";
		}
		$text .= "\n</div>";
		return $text;
	}

	public function convert( $toModel, $lossy = '' ) {
		if ( $toModel === CONTENT_MODEL_WIKITEXT && $lossy === 'lossy' ) {
			global $wgContLang;
			// using wgContLang is kind of icky. Maybe we should transclude
			// from mediawiki namespace, or give up on not splitting the
			// parser cache and just use {{int:... (?)
			$renderOpts = $this->getFullRenderListOptions();
			$text = $this->convertToWikitext( $wgContLang, $renderOpts );
			return ContentHandler::makeContent( $text, null, $toModel );
		} elseif ( $toModel === CONTENT_MODEL_JSON ) {
			return ContentHandler::makeContent( $this->getNativeData(), null, $toModel );
		}
		return parent::convert( $toModel, $lossy );
	}

	public function getDefaultOptions() {
		// FIXME implement
		return [
			'includeDesc' => false,
			'maxItems' => 5,
			'defaultSort' => 'random',
			'offset' => 0,
			'tags' => [],
		];
	}

	/**
	 * Sort a list
	 *
	 * @param &$items Array List to sort (sorted in-place)
	 * @param $mode String sort method
	 * @return Array sorted list
	 * @throws UnexpectedValueException on unrecognized mode
	 */
	private function sortList( &$items, $mode ) {
		switch ( $mode ) {
			case 'random':
				return $this->sortRandomly( $items );
			case 'natural':
				return $items;
			default:
				throw new UnexpectedValueException( "invalid sort mode" );
		}
	}

	/**
	 * Sort an array pseudo-randomly using an affine transform
	 *
	 * @param Array $items Stuff to sort (sorted in-place)
	 * @return Array
	 */
	private function sortRandomly( &$items ) {
		$totItems = count( $items );
		$rand1 = mt_rand( 1, $totItems - 1 );
		$rand2 = mt_rand( 0, $totItems - 1 );

		while ( $rand1 < $totItems - 1 && $rand1 % $totItems === 0 ) {
			// Make rand1 relatively prime to $totItems.
			$rand1++;
		}
		uksort( $items, function ( $a, $b ) use( $rand1, $rand2, $totItems ) {
			$a2 = ( $a * $rand1 + $rand2 ) % $totItems;
			$b2 = ( $b * $rand1 + $rand2 ) % $totItems;
			if ( $a2 === $b2 ) {
				// Really should not happen
				return 0;
			}
			return $a2 > $b2 ? 1 : -1;
		} );
		return $items;
	}

	/**
	 * Determine if an item matches a set of tags
	 *
	 * $tagSpecifier is a 2D array describing an AND of OR conditions
	 * e.g. $tagSpecifier = [ [ 'a', 'b' ], ['b', 'd'] ]
	 * means that any item must have the tags (a&&b) || (b&&d).
	 *
	 * @param $tagSpecifier Array What tags to check (aka $options['tags'])
	 * @param $itemTags Array What tags is this item tagged with
	 * @return boolean If the item matches
	 */
	private function matchesTag( array $tagSpecifier, array $itemTags ) {
		if ( !$tagSpecifier ) {
			return true;
		}
		$matchesAllGroups = true;
		foreach ( $tagSpecifier as $tagGroups ) {
			foreach ( $tagGroups as $tagAlt ) {
				$matchesOneAlternative = false;
				$itemTags;
				if ( in_array( $tagAlt, $itemTags ) ) {
					$matchesOneAlternative = true;
					break;
				}
			}
			if ( !$matchesOneAlternative ) {
				$matchesAllGroups = false;
				break;
			}
		}
		return $matchesAllGroups;
	}

	/**
	 * Convert JSON to markup that's easier for humans.
	 */
	public function convertToHumanEditable() {
		$this->decode();

		$output = $this->description;
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->getHumanEditableList();
		return $output;
	}

	/**
	 * Get the list of items in human editable form.
	 *
	 * @todo Should this be i18n-ized?
	 */
	public function getHumanEditableList() {
		$this->decode();

		$out = '';
		foreach ( $this->items as $item ) {
			$out .= $this->escapeForHumanEditable( $item->title );
			$out .= "|" . $this->escapeForHumanEditable( $item->notes );
			if ( isset( $item->link ) ) {
				if ( $item->link === false ) {
					$out .= "|nolink";
				} else {
					$out .= "|link=" . $this->escapeForHumanEditable( $item->link );
				}
			}
			if ( isset( $item->image ) ) {
				if ( $item->image === false ) {
					$out .= "|noimage";
				} else {
					$out .= "|image=" . $this->escapeForHumanEditable( $item->image );
				}
			}
			if ( isset( $item->tags ) ) {
				foreach ( (array)$item->tags as $tag ) {
					$out .= "|tag=" . $this->escapeForHumanEditable( $tag );
				}
			}
			if ( substr( $out, -1 ) === '|' ) {
				$out = substr( $out, 0, strlen( $out ) - 1 );
			}
			// Not doing sortkey as that's not really implemented.
			$out .= "\n";
		}
		return $out;
	}

	/**
	 * Escape characters used as separators in human editable mode.
	 *
	 * @todo Unclear if this is best approach. Alternative might be
	 *  to use &#xA; Or an obscure unicode character like ␊ (U+240A).
	 */
	private function escapeForHumanEditable( $text ) {
		if ( strpos( $text, '{{!}}' ) !== false ) {
			// Maybe we should use \| too, but that's not MW like.
			throw new MWContentSerializationException( "{{!}} in content" );
		}
		if ( strpos( $text, "\\\n" ) !== false ) {
			// @todo We don't currently handle this properly.
			throw new MWContentSerializationException( "Line ending with a \\" );
		}
		$text = strtr( $text, [
			"\n" => '\n',
			'\n'=> '\\\\n',
			'|' => '{{!}}'
		] );
		return $text;
	}

	private static function unescapeForHumanEditable( $text ) {
		$text = strtr( $text, [
			'\\\\n'=> "\\n",
			'\n' => "\n",
			'{{!}}' => '|'
		] );
		return $text;
	}

	/**
	 * Convert from human editable form into a (php) array
	 *
	 * @param $text String text to convert
	 * @return Array Result of converting it to native form
	 */
	public static function convertFromHumanEditable( $text ) {
		$res = [ 'options' => new StdClass, 'items' => [] ];

		$split = strrpos( $text, self::HUMAN_DESC_SPLIT );
		if ( $split === false ) {
			throw new MWContentSerializationException( "Missing list description" );
		}
		$dividerLength = strlen( self::HUMAN_DESC_SPLIT );
		$res['description'] = substr( $text, 0, $split );

		$list = substr( $text, $split + $dividerLength );
		$listLines = explode( "\n", $list );
		foreach ( $listLines as $line ) {
			$res['items'][] = self::convertFromHumanEditableItemLine( $line );
		}
		return $res;
	}

	private static function convertFromHumanEditableItemLine( $line ) {
		$parts = explode( "|", $line );
		$parts = array_map( [ __CLASS__, 'unescapeForHumanEditable' ], $parts );
		$itemRes = [ 'title' => $parts[0] ];
		if ( count( $parts ) > 1 ) {
			$itemRes['notes'] = $parts[1];
			$parts = array_slice( $parts, 2 );
			foreach ( $parts as $part ) {
				list( $key, $value ) = explode( '=', $part );
				switch ( $key ) {
				case 'nolink':
					$itemRes['link'] = false;
					break;
				case 'noimage':
					$itemRes['image'] = false;
					break;
				case 'tag':
					if ( !isset( $itemRes['tags'] ) ) {
						$itemRes['tags'] = [];
					}
					$itemRes['tags'][] = $value;
					break;
				case 'image':
				case 'link':
					$itemRes[$key] = $value;
					break;
				default:
					$context = wfEscapeWikiText( substr( $part, 30 ) );
					if ( strlen( $context ) === 30 ) {
						$context .= '...';
					}
					throw new MWContentSerializationException(
						"Unrecognized option for list item:" .
						wfEscapeWikiText( $key )
					);
				}
			}
		}
		return $itemRes;
	}

	/**
	 * Function to handle {{#trancludelist:Page name|options...}} calls
	 */
	public static function transcludeHook( $parser, $pageName = '' ) {
		$args = func_get_args();
		$args = array_splice( $args, 2 );
		$options = [];
		$title = Title::newFromText( $pageName );
		$lang = $parser->getFunctionLang();

		if ( !$title || $title->getContentModel() !== __CLASS__ ) {
			// This is interpreted as wikitext, so is safe.
			return Html::rawElement( 'div', [ 'class' => 'error' ],
				wfMessage( 'collaborationkit-listcontent-notlist' )
					->params( $title->getPrefixedText() )
					->inLanguage( $lang )
					->text()
			);
		}
		$tagCount = 0;
		foreach ( $args as $argument ) {
			if ( strpos( $argument, '=' ) === false ) {
				continue;
			}
			// If we need everything i18n-ized, this could be
			// replaced with magic words.
			list( $name, $value ) = explode( '=', $argument, 2 );
			if ( $name === 'tags' ) {
				$tagList = explode( '+', $value );
				if ( !isset( $options['tags'] ) ) {
					$options['tags'] = [];
				}
				$options['tags'][] = $tagList;
				$tagCount += count( $tagList );
			} elseif ( self::validateOption( $name, $value ) ) {
				$options[$name] = $value;
			}
		}

		if ( $tagCount > self::MAX_TAGS ) {
			return Html::rawElement( 'div', [ 'class' => 'error' ],
				wfMessage( 'collaborationkit-listcontent-toomanytags' )
					->numParams( self::MAX_TAGS, $tagCount )
					->inLanguage( $lang )
					->text()
			);
		}

		$wikipage = WikiPage::Factory( $title );
		$content = $wikipage->getContent();
		if ( !$content instanceof CollaborationListContent ) {
			// We already checked this, so this should not happen...
			return Html::rawElement( 'div', [ 'class' => 'error' ],
				wfMessage( 'collaborationkit-listcontent-notlist' )
					->params( $title->getPrefixedText() )
					->inLanguage( $lang )
					->text()
			);
		}

		if ( ( isset( $options['defaultSort'] )
			&& $options['defaultSort'] === 'random' )
			|| $content->getDefaultOptions()['defaultSort'] === 'random'
		) {
			$parser->getOutput()->updateCacheExpiry( self::RANDOM_CACHE_EXPIRY );
		}
		$parser->getOutput()->addTemplate( $title, $wikipage->getId(), $wikipage->getLatest() );
		$res = $content->convertToWikitext( $lang, $options );
		return [ $res, 'noparse' => false ];
	}

	/**
	 * Hack to allow styles to be loaded from plain transclusion.
	 *
	 * We don't have access to a parser object in getWikitextForTransclusion().
	 * So instead we put <collaborationkitloadliststyles/> on the page, which
	 * calls this.
	 *
	 * @param $content String Input to parser hook
	 * @param $attribs Array
	 * @param $parser Parser
	 */
	public static function loadStyles( $content, array $attributes, Parser $parser ) {
		$parser->getOutput()->addModuleStyles( 'ext.CollaborationKit.list.styles' );
		$parser->getOutput()->addModules( 'ext.CollaborationKit.icons' );
		return '';
	}

	/**
	 * Hook used to determine if current user should be given the edit interface
	 * for a page.
	 *
	 * @todo Not clear if this is the best hook to use. onBeforePageDisplay
	 *  doesn't have easy access to oldid
	 *
	 * @param $output OutputPage
	 */
	public static function onArticleViewHeader( Article $article ) {
		$title = $article->getTitle();
		$context = $article->getContext();
		$user = $context->getUser();
		$output = $context->getOutput();
		$action = $context->getRequest()->getVal( 'action', 'view' );

		// @todo Does not trigger on perma-link to current revision
		//  not sure if that's a desired behaviour or not.
		if ( $title->getContentModel() === __CLASS__
			&& $action === 'view'
			&& $title->getArticleId() !== 0
			&& $article->getOldID() === 0 /* current rev */
			&& $title->userCan( 'edit', $user, 'quick' )
		) {
			$output->addJsConfigVars( 'wgEnableCollaborationKitListEdit', true );
			$output->addModules( 'ext.CollaborationKit.list.edit' );
			$output->preventClickjacking();
		}
	}

	/**
	 * Hook to use custom edit page for lists
	 *
	 * @param $page Page
	 * @param $user User
	 */
	public static function onCustomEditor( Page $page, User $user ) {
		if ( $page->getContentModel() === __CLASS__ ) {
			$editor = new CollaborationListContentEditor( $page );
			$editor->edit();
			return false;
		}
	}
}
