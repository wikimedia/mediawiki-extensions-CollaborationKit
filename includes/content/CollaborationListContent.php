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

		$text = "__NOEDITSECTION__\n__NOTOC__";
		if ( $includeDesc ) {
			$text .= $this->getDescription() . "\n";
		}
		if ( count( $this->items ) === 0 ) {
			$text .= "<hr>\n{{mediawiki:collaborationkit-listempty}}\n";
		}
		$curItem = 0;
		$offset = $options['defaultSort'] === 'random' ? 0 : $options['offset'];

		$sortedItems = $this->items;
		$this->sortList( $sortedItems, $options['defaultSort'] );

		foreach ( $sortedItems as $item ) {
			if ( $offset !== 0 ) {
				$offset--;
				continue;
			}
			$curItem++;
			if ( $maxItems !== false && $maxItems < $curItem ) {
				break;
			}

			$itemTags = $item->tags ? $item->tags : [];
			if ( !$this->matchesTag( $options['tags'], $itemTags ) ) {
				continue;
			}

			$titleForItem = null;
			if ( !isset( $item->link ) ) {
				$titleForItem = Title::newFromText( $item->title );
			} elseif ( $item->link !== false ) {
				$titleForItem = Title::newFromText( $item->link );
			}
			if ( $titleForItem ) {
				$text .= "==[[" . $titleForItem->getPrefixedDBkey() . "|"
					. wfEscapeWikiText( $item->title ) . "]]==\n";
			} else {
				$text .= "==" . wfEscapeWikiText( $item->title ) . "==\n";
			}

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

			if ( $image ) {
				$text .= '[[File:' . $image->getName() . "|left|100x100px|alt=]]\n";
			}

			if ( is_string( $item->notes ) ) {
				$text .= $item->notes . "\n";
			}

			if ( is_array( $item->tags ) && count( $item->tags ) ) {
				$text .= "\n<div class='toccolours' style='display:inline-block'>" .
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
			if ( $image ) {
				$text .= "<br style='clear:both'>\n";
			}
		}
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
	private function matchesTag( Array $tagSpecifier, Array $itemTags ) {
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
	 * Function to handle {{#trancludelist:Page name|options...}} calls
	 */
	public static function transcludeHook( $parser, $pageName = '' ) {
		$args = array_splice( func_get_args(), 2 );
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
		return $content->convertToWikitext( $lang, $options );
	}
}
