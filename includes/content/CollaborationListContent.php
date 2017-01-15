<?php

/**
 * Important design assumption: This class assumes lists are small
 * (e.g. Average case < 500 items, outliers < 2000)
 *
 * Schema is found in CollaborationListContentSchema.php.
 *
 */
class CollaborationListContent extends JsonContent {

	const MAX_LIST_SIZE = 2000; // Maybe should be a config option.
	const RANDOM_CACHE_EXPIRY = 28800; // 8 hours
	const MAX_TAGS = 50;

	// Splitter for description and options
	const HUMAN_DESC_SPLIT = "\n-----------------------\n";
	// Splitter denoting the beginning of a list column
	const HUMAN_COLUMN_SPLIT = "\n---------~-~---------\n";
	// Splitter denoting the beginning og the list itself within the column
	const HUMAN_COLUMN_SPLIT2 = "\n---------------------\n";

	/** @var $decoded boolean Have we decoded the data yet */
	private $decoded = false;
	/** @var $description String Descripton wikitext */
	protected $description;
	/** @var $options StdClass Options for page */
	protected $options;
	/** @var $items Array List of columns */
	protected $columns;
	/** @var $displaymode String The variety of list */
	protected $displaymode;

	function __construct( $text, $type = 'CollaborationListContent' ) {
		parent::__construct( $text, $type );
	}

	/**
	 * Decode and validate the contents.
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		$listSchema = include __DIR__ . '/CollaborationListContentSchema.php';
		if ( !parent::isValid() ) {
			return false;
		}
		$status = $this->getData();
		if ( !is_object( $status ) || !$status->isOk() ) {
			return false;
		}
		$data = $status->value;
		// FIXME: The schema should be checking for required fields but for some reason that doesn't work
		// This may be an issue with EventLogging
		if ( !isset( $data->description ) || !isset( $data->columns ) || !isset( $data->options ) || !isset( $data->displaymode ) ) {
			return false;
		}
		$jsonAsArray = json_decode( json_encode( $data ), true );
		try {
			EventLogging::schemaValidate( $jsonAsArray, $listSchema );
			return true;
		} catch ( JsonSchemaException $e ) {
			return false;
		}
		return false;
	}

	private static function validateOption( $name, &$value ) {
		$listSchema = include __DIR__ . '/CollaborationListContentSchema.php';

		// Special handling for DISPLAYMODE
		if ( $name == 'DISPLAYMODE' ) {
			if ( $value == 'members' || $value == 'normal' || $value == 'error' ) {
				return true;
			}
			return false;
		}

		// Force intrepretation as boolean for certain options
		if ( $name == "includedesc" ) {
			$value = (bool)$value;
		}

		// Set up a dummy CollaborationListContent array featuring the options being validated
		$toValidate = [
			'displaymode' => 'normal',
			'description' => '',
			'columns' => [],
			'options' => [ $name => $value ]
		];
		return EventLogging::schemaValidate( $toValidate, $listSchema );
	}

	/**
	 * Format json
	 *
	 * Do not escape < and > it's unnecessary and ugly
	 * @return string
	 */
	public function beautifyJSON() {
		return FormatJson::encode( $this->getData()->getValue(), true, FormatJson::ALL_OK );
	}

	/**
	* Beautifies JSON and does subst: prior to save.
	*
	* @param $title Title Title
	* @param $user User User
	* @param $popts ParserOptions
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
	 * Decode the JSON contents and populate protected variables.
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return;
		}
		$data = $this->getData()->value;
		if ( !$this->isValid() ) {
			$this->displaymode = 'error';
			if ( !parent::isValid() ) {
				// It's not even valid json
				$this->errortext = htmlspecialchars( $this->getNativeData() );
			} else {
				$this->errortext = FormatJson::encode( $data, true, FormatJson::ALL_OK );
			}
		} else {
			$this->displaymode = $data->displaymode;
			$this->description = $data->description;
			$this->options = $data->options;
			$this->columns = $data->columns;
		}
		$this->decoded = true;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		$this->decode();
		return $this->description;
	}

	/**
	 * Fill $output with information derived from the content.
	 * @param $title Title
	 * @param $revId int
	 * @param $options ParserOptions
	 * @param $generateHtml bool
	 * @param $output ParserOutput
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
		$listOptions = $this->getFullRenderListOptions()
			+ (array)$this->options
			+ $this->getDefaultOptions();
		$text = $this->convertToWikitext( $lang, $listOptions );
		$output = $wgParser->parse( $text, $title, $options, true, true, $revId );
		if ( $this->displaymode == 'members' ) {
			$isMemberList = true;
		} else {
			$isMemberList = false;
		}
		$output->addJsConfigVars( 'wgCollaborationKitIsMemberList', $isMemberList );
	}

	/**
	 * Get rendering options to use when directly viewing the list page
	 *
	 * These are used on direct page views, and plain wikitext
	 * transclusions. They are not used when using the parser function.
	 *
	 * @todo FIXME These should maybe not be used during transclusion.
	 * @return Array Options
	 */
	private function getFullRenderListOptions() {
		return $listOptions = [
			'includeDesc' => true,
			'maxItems' => false,
			'defaultSort' => 'natural',
		];
	}

	/**
	 * Convert the JSON to wikitext.
	 *
	 * @param $lang Language The (content) language to render the page in.
	 * @param $options Array Options to override the default transclude options
	 * @return string The wikitext
	 */
	public function convertToWikitext( Language $lang, $options = [] ) {
		$this->decode();
		$options = $options + $this->getDefaultOptions();
		$maxItems = $options['maxItems'];
		$includeDesc = $options['includeDesc'];

		// If this is an error-type list (i.e. a schema-violating blob),
		// just return the plain JSON.

		if ( $this->displaymode == 'error' ) {
			$errorWikitext = '<div class=errorbox>' .
				wfMessage( 'collaborationkit-list-invalid' )->inLanguage( $lang )->plain() .
				"</div>\n<pre>" .
				$this->errortext .
				'</pre>';
			return $errorWikitext;
		}

		// Hack to force style loading even when we don't have a Parser reference.
		$text = "<collaborationkitloadliststyles/>\n";

		if ( $includeDesc ) {
			$text .= $this->getDescription() . "\n";
		}
		if ( count( $this->columns ) === 0 ) {
			$text .= "\n{{mediawiki:collaborationkit-list-isempty}}\n";
			return $text;
		}

		if ( $this->displaymode === 'members' && count( $this->columns ) === 1 ) {
			$columns = $this->sortUsersIntoColumns( $this->columns[0] );
		} else {
			$columns = $this->columns;
		}

		$listclass = count( $columns ) > 1 ? 'mw-ck-multilist' : 'mw-ck-singlelist';
		$text .= '<div class="mw-ck-list ' . $listclass . '">' . "\n";
		$offset = $options['defaultSort'] === 'random' ? 0 : $options['offset'];
		foreach ( $columns as $colId => $column ) {
			$text .= Html::openElement( 'div', [
				'class' => 'mw-ck-list-column',
				'data-collabkit-column-id' => $colId
			] ) . "\n";
			if ( isset( $column->label ) && $column->label !== '' ) {
				$text .= "=== {$column->label} ===\n";
			}
			if ( isset( $column->notes ) && $column->notes !== '' ) {
				$text .= "\n{$column->notes}\n\n";
			}

			if ( count( $column->items ) === 0 ) {
				$text .= "\n{{mediawiki:collaborationkit-list-emptycolumn}}\n";
				continue;
			}

			$curItem = 0;

			$sortedItems = $column->items;
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
					"class" => "mw-ck-list-item",
					"data-collabkit-item-title" => $item->title
				] );
				if ( $options['mode'] !== 'no-img' ) {
					$image = null;
					if ( !isset( $item->image ) && $titleForItem ) {
						if ( class_exists( 'PageImages' ) ) {
							$image = PageImages::getPageImage( $titleForItem );
						}
					} elseif ( isset( $item->image ) && is_string( $item->image ) ) {
						$imageTitle = Title::newFromText( $item->image, NS_FILE );
						if ( $imageTitle ) {
							$image = wfFindFile( $imageTitle );
						}
					}

					$text .= '<div class="mw-ck-list-image">';
					if ( $image ) {
						// Important: If you change the width of the image
						// you also need to change it in the stylesheet.
						$text .= '[[File:' . $image->getName() . "|left|64px|alt=]]\n";
					} else {
						if ( $this->displaymode == 'members' ) {
							$placeholderIcon = 'mw-ck-icon-user-grey2';
						} else {
							$placeholderIcon = 'mw-ck-icon-page-grey2';
						}
						$text .= Html::element( 'div', [
							"class" => [
								'mw-ck-list-noimageplaceholder',
								$placeholderIcon
							]
						] );
					}
					$text .= '</div>';
				}

				$text .= '<div class="mw-ck-list-container">';
				// Question: Arguably it would be more semantically correct to use
				// an <Hn> element for this. Would that be better? Unclear.
				$text .= '<div class="mw-ck-list-title">';
				if ( $titleForItem ) {
					if ( $this->displaymode == 'members'
						&& !isset( $item->link )
						&& $titleForItem->inNamespace( NS_USER )
					) {
						$titleText = $titleForItem->getText();
					} else {
						$titleText = $item->title;
					}
					$text .= "[[:" . $titleForItem->getPrefixedDBkey() . "|"
						. wfEscapeWikiText( $titleText ) . "]]";
				} else {
					$text .=  wfEscapeWikiText( $item->title );
				}
				$text .= "</div>\n";
				$text .= '<div class="mw-ck-list-notes">' . "\n";
				if ( isset( $item->notes ) && is_string( $item->notes ) ) {
					$text .= $item->notes . "\n";
				}

				if ( isset( $item->tags ) && is_array( $item->tags ) && count( $item->tags ) ) {
					$text .= "\n<div class='toccolours mw-ck-list-tags'>" .
						wfMessage( 'collaborationkit-list-taglist' )
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

	/**
	 * Default rendering options.
	 *
	 * These are used when rendering a list and no option
	 * value was specified. getFullRenderListOptions() will
	 * override some of these values when a list page is directly
	 * viewed.
	 *
	 * Any new option must be added to this list.
	 *
	 * @return Array default rendering options to use.
	 */
	public function getDefaultOptions() {
		// FIXME implement
		// FIXME use defaults from schema instead of hardcoded values
		return [
			'includeDesc' => false,
			'maxItems' => 5,
			'defaultSort' => 'random',
			'offset' => 0,
			'tags' => [],
			'mode' => 'normal'
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
	 * @param $items Array Stuff to sort (sorted in-place)
	 * @return Array
	 */
	private function sortRandomly( &$items ) {
		$totItems = count( $items );
		if ( count( $items ) > 1 ) { // No point in randomizing if only one item
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
		}
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
		$output .= $this->getHumanOptions();
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->getHumanEditableList();
		return $output;
	}

	/**
	 * Output the options in human readable form
	 *
	 * @return String key=value pairs separated by newlines.
	 */
	private function getHumanOptions() {
		$this->decode();
		$ret = '';
		foreach ( $this->options as $opt => $value ) {
			$ret .= $opt . '=' . $value . "\n";
		}
		// This might be a bad idea, but displaymode (not considered
		// an "option" but a separate attribute) is going to piggy-
		// back off of this method. Allcaps is to signify its specialness.
		$ret .= 'DISPLAYMODE=' . $this->displaymode . "\n";
		return $ret;
	}

	/**
	 * Get the list of items in human editable form.
	 *
	 * @todo i18n-ize
	 */
	public function getHumanEditableList() {
		$this->decode();

		$out = '';
		foreach ( $this->columns as $column ) {
			// Use two to separate columns
			$out .= self::HUMAN_COLUMN_SPLIT;
			if ( isset( $column->label ) ) {
				$out .= $this->escapeForHumanEditable( $column->label );
			} else {
				$out .= 'column';
			}
			if ( isset( $column->notes ) ) {
				$out .= "|notes=" . $this->escapeForHumanEditable( $column->notes );
			}
			$out .= self::HUMAN_COLUMN_SPLIT2;

			foreach ( $column->items as $item ) {
				$out .= $this->escapeForHumanEditable( $item->title );
				if ( isset ( $item->notes ) ) {
					$out .= "|" . $this->escapeForHumanEditable( $item->notes );
				} else {
					$out .= "|";
				}
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
	 * @param $options String Human readable options
	 */
	private static function parseHumanOptions( $options ) {
		$finalList = [];
		$optList = explode( "\n", $options );
		foreach ( $optList as $line ) {
			$splitLine = explode( '=', $line, 2 );
			if ( count( $splitLine ) !== 2 ) {
				continue;
			}
			$name = trim( $splitLine[0] );
			$value = trim( $splitLine[1] );
			if ( self::validateOption( $name, $value ) ) {
				$finalList[$name] = $value;
			}
		}
		return (object)$finalList;
	}

	/**
	 * Convert from human editable form into a (php) array
	 *
	 * @param $text String text to convert
	 * @return Array Result of converting it to native form
	 */
	public static function convertFromHumanEditable( $text ) {
		$res = [ 'columns' => [] ];

		$split2 = strrpos( $text, self::HUMAN_DESC_SPLIT );
		if ( $split2 === false ) {
			throw new MWContentSerializationException( "Missing list description" );
		}

		$split1 = strrpos( $text, self::HUMAN_DESC_SPLIT, -strlen( $text ) + $split2 - 1 );
		if ( $split1 === false ) {
			throw new MWContentSerializationException( "Missing list description" );
		}
		$dividerLength = strlen( self::HUMAN_DESC_SPLIT );

		$optionLength = $split2 - ( $split1 + $dividerLength );
		$optionString = substr( $text, $split1 + $dividerLength, $optionLength );
		$res['options'] = self::parseHumanOptions( $optionString );

		if ( isset ( $res['options']->DISPLAYMODE ) ) {
			$res[ 'displaymode' ] = $res['options']->DISPLAYMODE;
			unset( $res['options']->DISPLAYMODE );
		} else {
			throw new MWContentSerializationException( "Missing list displaymode" );
		}

		$res['description'] = substr( $text, 0, $split1 );
		$columnText = substr( $text, $split2 + $dividerLength );

		// Put \n back on beginning so it still explodes properly after general trim
		$columnText = "\n" . $columnText;
		$columns = explode( self::HUMAN_COLUMN_SPLIT, $columnText );
		foreach ( $columns as $column ) {
			// Skip empty lines
			if ( trim( $column ) !== '' ) {
				$res['columns'][] = self::convertFromHumanEditableColumn( $column );
			}
		}

		return $res;
	}

	private static function convertFromHumanEditableColumn( $column ) {
		$columnItem = [ 'items' => [] ];

		$columnContent = explode( self::HUMAN_COLUMN_SPLIT2, $column );
		if ( count( $columnContent ) == 1 ) {
			return $columnItem;
		}

		$parts = explode( "|", $columnContent[0] );

		$parts = array_map( [ __CLASS__, 'unescapeForHumanEditable' ], $parts );

		if ( $parts[0] != 'column' ) {
			$columnItem[ 'label' ] = $parts[0];
		}

		$parts = array_slice( $parts, 1 );

		if ( count( $parts ) > 0 ) {
			foreach ( $parts as $part ) {
				if ( $part == 'column' ) {
					continue;
				}
				list( $key, $value ) = explode( '=', $part );

				switch ( $key ) {
				case 'notes':
					$columnItem[$key] = $value;
					break;
				default:
					$context = wfEscapeWikiText( substr( $part, 30 ) );
					if ( strlen( $context ) === 30 ) {
						$context .= '...';
					}
					throw new MWContentSerializationException(
						"Unrecognized option for column:" .
						wfEscapeWikiText( $key )
					);
				}
			}
		}

		$listLines = explode( "\n", $columnContent[1] );
		foreach ( $listLines as $line ) {
			// Skip empty lines
			if ( trim( $line ) !== '' ) {
				$columnItem['items'][] = self::convertFromHumanEditableItemLine( $line );
			}
		}

		return $columnItem;
	}

	private static function convertFromHumanEditableItemLine( $line ) {
		$parts = explode( "|", $line );
		$parts = array_map( [ __CLASS__, 'unescapeForHumanEditable' ], $parts );
		$itemRes = [ 'title' => $parts[0] ];
		if ( count( $parts ) > 1 ) {
			// If people are using batch editor, they might define an image etc. despite lack of a note
			// This is to catch that and prevent weirdness.
			$testExplosion = explode( "=", $parts[1] );
			if ( in_array( $testExplosion[0], [ 'image', 'link', 'tags', 'sortkey' ] ) ) {
				$itemRes[ $testExplosion[0] ] = $testExplosion[1];
				$itemRes['notes'] = '';
			} else {
				$itemRes['notes'] = $parts[1];
			}
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
		} else {
			$itemRes['notes'] = '';
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
				wfMessage( 'collaborationkit-list-notlist' )
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
				wfMessage( 'collaborationkit-list-toomanytags' )
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
				wfMessage( 'collaborationkit-list-notlist' )
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
	 * Sort users into active/inactive column
	 *
	 * @param $column Array An array containing key items, which
	 *  is an array of stdClass's representing each list item.
	 *  Each of these has a key named title which contains
	 *  a user name (including namespace). May have non-users too.
	 * @return Array Two column structure sorted active/inactive.
	 * @todo Should link property be taken into account as actual name?
	 */
	private function sortUsersIntoColumns( $column ) {
		$nonUserItems = [];
		$userItems = [];
		foreach ( $column->items as $item ) {
			$title = Title::newFromText( $item->title );
			if ( !$title ||
				!$title->inNamespace( NS_USER ) ||
				$title->isSubpage()
			) {
				$nonUserItems[] = $item;
			} else {
				$userItems[ $title->getDBKey() ] = $item;
			}
		}
		$res = $this->filterActiveUsers( $userItems );
		$inactiveFlatList = array_merge( array_values( $res['inactive'] ), $nonUserItems );

		$activeColumn = (object)[
			'items' => array_values( $res['active'] ),
			'label' => wfMessage( 'collaborationkit-column-active' )->inContentLanguage()->text(),
		];
		$inactiveColumn = (object)[
			'items' => $inactiveFlatList,
			'label' => wfMessage( 'collaborationkit-column-inactive' )->inContentLanguage()->text(),
		];

		return [ $activeColumn, $inactiveColumn ];
	}

	/**
	 * Filter users into active and inactive.
	 *
	 * @note The results of this function get stored in parser cache.
	 * @param $userList Array of user dbkeys => stdClass
	 * @return Array [ 'active' => [..], 'inactive' => '[..]' ]
	 */
	private function filterActiveUsers( $userList ) {
		$users = array_keys( $userList );
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'querycachetwo',
			'qcc_title',
			[
				'qcc_namespace' => NS_USER,
				// TODO: Perhaps should use batching.
				'qcc_title' => $users,
				'qcc_type' => 'activeusers'
			],
			__METHOD__
		);

		$active = [];
		foreach ( $res as $row ) {
			$active[$row->qcc_title] = $userList[$row->qcc_title];
			unset( $userList[$row->qcc_title] );
		}
		return [ 'active' => $active, 'inactive' => $userList ];
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
