<?php

/**
 * A content model for representing lists of wiki pages in JSON.
 *
 * This content model is used to prepare lists of pages (i.e., of
 * members' user pages or of wiki pages to work on) and associated
 * metadata while separating content from presentation.
 * Features associated JavaScript modules allowing for quick
 * manipulation of list contents.
 * Important design assumption: This class assumes lists are small
 * (e.g. Average case < 500 items, outliers < 2000)
 * Schema is found in CollaborationListContentSchema.php.
 *
 * @file
 */

use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\MediaWikiServices;
use PageImages\PageImages;

class CollaborationListContent extends JsonContent {

	const MAX_LIST_SIZE = 2000; // Maybe should be a config option.
	const RANDOM_CACHE_EXPIRY = 28800; // 8 hours
	const MAX_TAGS = 50;

	// Splitter denoting the beginning of a list column
	const HUMAN_COLUMN_SPLIT = "\n---------~-~---------\n";
	// Splitter denoting the beginning of the list itself within the column
	const HUMAN_COLUMN_SPLIT2 = "\n---------------------\n";

	/** @var bool Have we decoded the data yet */
	private $decoded = false;
	/** @var string Descripton wikitext */
	protected $description;
	/** @var StdClass Options for page */
	protected $options;
	/** @var $items array List of columns */
	protected $columns;
	/** @var string The variety of list */
	protected $displaymode;

	/** @var string Error message text */
	protected $errortext;

	/**
	 * @param string $text
	 * @param string $type
	 */
	public function __construct( $text, $type = 'CollaborationListContent' ) {
		parent::__construct( $text, $type );
	}

	/**
	 * Decode and validate the contents.
	 *
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		$listSchema = include __DIR__ . '/CollaborationListContentSchema.php';
		if ( !parent::isValid() ) {
			return false;
		}
		$status = $this->getData();
		if ( !is_object( $status ) || !$status->isOK() ) {
			return false;
		}
		$data = $status->value;
		// FIXME: The schema should be checking for required fields but for some
		// reason that doesn't work. This may be an issue with EventLogging
		if (
			!isset( $data->description )
			|| !isset( $data->columns )
			|| !isset( $data->options )
			|| !isset( $data->displaymode )
		) {
			return false;
		}

		$jsonAsArray = json_decode( json_encode( $data ), true );

		try {
			EventLogging::schemaValidate( $jsonAsArray, $listSchema );
			// FIXME: The schema should be enforcing data type requirements, but
			// it isn't. Again, this is probably EventLogging.
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			foreach ( $jsonAsArray['columns'] as $column ) {
				if ( !is_array( $column ) ) {
					return false;
				} else {
					foreach ( $column['items'] as $item ) {
						if ( !is_array( $item ) ) {
							return false;
						}
					}
				}
			}
			return true;
		} catch ( JsonSchemaException $e ) {
			return false;
		}
	}

	/**
	 * Validates a list configuration option against the schema.
	 *
	 * @param string $name The name of the parameter
	 * @param mixed &$value The value of the parameter
	 * @return bool Whether the configuration option is valid.
	 */
	private static function validateOption( $name, &$value ) {
		$listSchema = include __DIR__ . '/CollaborationListContentSchema.php';

		// Special handling for DISPLAYMODE
		if ( $name == 'DISPLAYMODE' ) {
			return ( $value == 'members' || $value == 'normal' || $value == 'error' );
		}

		// Force intrepretation as boolean for certain options
		if ( $name == 'includedesc' ) {
			$value = (bool)$value;
		}

		// Set up a dummy CollaborationListContent array featuring the options
		// being validated.
		$toValidate = [
			'displaymode' => 'normal',
			'description' => '',
			'columns' => [],
			'options' => [ $name => $value ]
		];
		return EventLogging::schemaValidate( $toValidate, $listSchema );
	}

	/**
	 * Format JSON
	 *
	 * Do not escape < and > it's unnecessary and ugly
	 * @return string
	 */
	public function beautifyJSON() {
		return FormatJson::encode(
			$this->getData()->getValue(),
			true,
			FormatJson::ALL_OK
		);
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
				$this->errortext = htmlspecialchars( $this->getText() );
			} else {
				$this->errortext = FormatJson::encode(
					$data,
					true,
					FormatJson::ALL_OK
				);
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
	 *
	 * @param Title $title
	 * @param int $revId Revision ID
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 */
	protected function fillParserOutput( Title $title, $revId,
		ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		$parser = MediaWikiServices::getInstance()->getParser();
		$this->decode();

		$lang = $options->getTargetLanguage();
		if ( !$lang ) {
			$lang = $title->getPageLanguage();
		}

		// If this is an error-type list (i.e. a schema-violating blob),
		// just return the plain JSON.
		if ( $this->displaymode == 'error' ) {
			$errorText = '<div class=errorbox>' .
				wfMessage( 'collaborationkit-list-invalid' )
					->inLanguage( $lang )
					->plain() .
				"</div>\n<pre>" .
				$this->errortext .
				'</pre>';
			$output = $parser->parse( $errorText, $title, $options, true, true,
				$revId );
			return;
		}

		$listOptions = $this->getFullRenderListOptions()
			+ (array)$this->options
			+ $this->getDefaultOptions();

		// Preparing page contents
		$text = $this->convertToWikitext( $lang, $listOptions );
		$output = $parser->parse( $text, $title, $options, true, true, $revId );

		$parser->addTrackingCategory( 'collaborationkit-list-tracker' );

		// Special JS variable if this is a member list
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
	 * @return array Options
	 */
	private function getFullRenderListOptions() {
		return [
			'includeDesc' => true,
			'maxItems' => false,
			'defaultSort' => 'natural',
		];
	}

	/**
	 * Convert the JSON to wikitext.
	 *
	 * @param Language $lang The (content) language to render the page in.
	 * @param array $options Options to override the default transclude options
	 * @return string The wikitext
	 */
	public function convertToWikitext( Language $lang, $options = [] ) {
		$this->decode();
		$options += $this->getDefaultOptions();
		$maxItems = $options['maxItems'];
		$includeDesc = $options['includeDesc'];
		$iconWidth = (int)$options['iconWidth'];

		// Hack to force style loading even when we don't have a Parser reference.
		$text = '<collaborationkitloadliststyles/>';

		// Ugly way to prevent unexpected column header TOCs and editsection
		// links from showing up
		$text .= "__NOTOC__ __NOEDITSECTION__\n";

		if ( $includeDesc ) {
			$text .= $this->getDescription() . "\n";
		}
		if ( $this->columns === null || count( $this->columns ) === 0 ) {
			$text .= "\n{{mediawiki:collaborationkit-list-isempty}}\n";
			return $text;
		}

		$columns = $this->columns;

		// Assign a UID to each list entry.
		$uidCounter = 0;
		foreach ( $columns as $colId => $column ) {
			foreach ( $column->items as $rowId => $row ) {
				$columns[$colId]->items[$rowId]->uid = $uidCounter;
				$uidCounter++;
			}
		}

		if ( $this->displaymode === 'members' && count( $this->columns ) === 1 ) {
			$columns = $this->sortUsersIntoColumns( $columns[0] );
		}

		$columns = $this->filterColumns( $columns, $options['columns'] );

		$listclass = count( $columns ) > 1 ? 'mw-ck-multilist' : 'mw-ck-singlelist';
		$text .= '<div class="mw-ck-list ' . $listclass . '">' . "\n";
		$offsetRule = $options['defaultSort'] === 'random' ? 0 : $options['offset'];
		foreach ( $columns as $colId => $column ) {
			$offset = $offsetRule;  // Resetting value after each column is processed
			$text .= Html::openElement( 'div', [
				'class' => 'mw-ck-list-column',
				'data-collabkit-column-id' => $colId
			] ) . "\n";
			$text .= '<div class="mw-ck-list-column-header">' . "\n";
			if ( $options['showColumnHeaders'] && isset( $column->label )
				&& $column->label !== ''
			) {
				$text .= "=== {$column->label} ===\n";
			}
			if (
				isset( $column->notes ) &&
				$column->notes !== '' &&
				$options['showColumnHeaders']
			) {
				$text .= "<div class=\"mw-ck-list-notes\">{$column->notes}</div>\n";
			}
			$text .= "</div>\n";

			if ( count( $column->items ) === 0 ) {
				$text .= "\n<div class=\"mw-ck-list-item\">";
				$text .= "{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n";
			} else {
				$curItem = 0;

				$sortedItems = $column->items;
				$this->sortList( $sortedItems, $options['defaultSort'] );

				$itemCounter = 0;
				foreach ( $sortedItems as $item ) {
					if ( $offset !== 0 ) {
						$offset--;
						continue;
					}
					$curItem++;
					if ( $maxItems !== false && $maxItems < $curItem ) {
						break;
					}

					$itemTags = $item->tags ?? [];
					if ( !$this->matchesTag( $options['tags'], $itemTags ) ) {
						continue;
					}

					$titleForItem = null;
					if ( !isset( $item->link ) ) {
						$titleForItem = Title::newFromText( $item->title );
					} elseif ( $item->link !== false ) {
						$titleForItem = Title::newFromText( $item->link );
					}
					$adjustedIconWidth = $iconWidth * 1.3;
					$text .= Html::openElement( 'div', [
						'class' => 'mw-ck-list-item',
						'data-collabkit-item-title' => $item->title,
						'data-collabkit-item-id' => $colId . '-' . $itemCounter,
						'data-collabkit-item-uid' => $item->uid
					] );
					$itemCounter++;
					if ( $options['mode'] !== 'no-img' ) {
						if ( isset( $item->image ) ) {
							$text .= static::generateImage(
								$item->image,
								$this->displaymode,
								$titleForItem,
								$iconWidth
							);
						} else {
							// Use fallback mechanisms
							$text .= static::generateImage(
								null,
								$this->displaymode,
								$titleForItem,
								$iconWidth
							);
						}
					}

					$text .= '<div class="mw-ck-list-container">';
					// Question: Arguably it would be more semantically correct to
					// use an <Hn> element for this. Would that be better? Unclear.
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
						$text .= '[[:' . $titleForItem->getPrefixedDBkey() . '|'
							. wfEscapeWikiText( $titleText ) . ']]';
					} else {
						$text .= wfEscapeWikiText( $item->title );
					}
					$text .= "</div>\n";
					$text .= '<div class="mw-ck-list-notes">' . "\n";
					if ( isset( $item->notes ) && is_string( $item->notes ) ) {
						$text .= $item->notes . "\n";
					}

					if ( isset( $item->tags ) && is_array( $item->tags )
						&& count( $item->tags )
					) {
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
			}
			if ( $this->displaymode != 'members' ) {
				$text .= "\n<div class=\"mw-ck-list-additem-container\"></div>";
			}
			$text .= "\n</div>";
		}
		$text .= "\n</div>";
		if ( $this->displaymode == 'members' ) {
			$text .= "\n<div class=\"mw-ck-list-additem-container\"></div>";
		}
		return $text;
	}

	/**
	 * Invokes CollaborationKitImage::makeImage with fallback criteria
	 *
	 * @param string|null $definedImage The filename given in the list item
	 * @param string $displayMode Type of list (members or otherwise)
	 * @param Title|null $title Title object of the list item
	 * @param int $size The width of the icon image. Default is 32px;
	 * @return string HTML
	 */
	protected static function generateImage( $definedImage, $displayMode, $title,
		$size = 32
	) {
		$size = (int)$size;  // Just in case
		$image = null;
		$iconColour = '';
		$linkOrNot = true;

		// Step 1: Use the defined image, assuming it's valid
		if ( $definedImage !== null && is_string( $definedImage ) ) {
			$imageTitle = Title::newFromText( $definedImage, NS_FILE );
			if ( $imageTitle ) {
				$imageObj = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $imageTitle );
				if ( $imageObj ) {
					$image = $imageObj->getName();
				}
			}
		}

		// Step 2: No defined image / invalid defined image? Use PageImages if possible.
		if ( $image === null && $title && ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$queryPageImages = PageImages::getPageImage( $title );
			if ( $queryPageImages !== false ) {
				$image = $queryPageImages->getName();
			}
		}

		// Step 3: None of the above work? Time for fallback icons.
		if ( $image === null ) {
			$iconColour = 'lightgrey';
			$linkOrNot = false;
			if ( $displayMode == 'members' ) {
				$image = 'user';
			} else {
				$image = 'page';
			}
		}

		return CollaborationKitImage::makeImage(
			$image,
			$size,
			[
				'css' => "width:{$size}px; height:{$size}px;",
				'classes' => [ 'mw-ck-list-image', 'thumbinner' ],
				'colour' => $iconColour,
				'link' => $linkOrNot,
				'renderAsWikitext' => true,
				'optimizeForSquare' => true
			]
		);
	}

	/**
	 * Converts between different text-based content models
	 *
	 * @param string $toModel The desired content model, use the
	 *  CONTENT_MODEL_XXX flags.
	 * @param string $lossy Flag, set to "lossy" to allow lossy conversion.
	 *  If lossy conversion is not allowed, full round-trip conversion is expected
	 *  to work without losing information.
	 * @return Content|bool A content object with the content model $toModel.
	 */
	public function convert( $toModel, $lossy = '' ) {
		if ( $toModel === CONTENT_MODEL_WIKITEXT && $lossy === 'lossy' ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			// Maybe we should transclude from MediaWiki namespace, or give
			// up on not splitting the parser cache and just use {{int:... (?)
			$renderOpts = $this->getFullRenderListOptions();
			$text = $this->convertToWikitext( $contLang, $renderOpts );
			return ContentHandler::makeContent( $text, null, $toModel );
		} elseif ( $toModel === CONTENT_MODEL_JSON ) {
			return ContentHandler::makeContent(
				$this->getText(),
				null,
				$toModel
			);
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
	 * @return array default rendering options to use.
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
			'mode' => 'normal',
			'columns' => [],
			'showColumnHeaders' => true,
			'iconWidth' => 32
		];
	}

	/**
	 * Sort a list
	 *
	 * @param array &$items List to sort (sorted in-place)
	 * @param string $mode sort method
	 * @return array sorted list
	 * @throws UnexpectedValueException on unrecognized mode
	 */
	private function sortList( &$items, $mode ) {
		switch ( $mode ) {
			case 'random':
				return $this->sortRandomly( $items );
			case 'natural':
				return $items;
			default:
				throw new UnexpectedValueException( 'invalid sort mode' );
		}
	}

	/**
	 * Filter displayed columns
	 *
	 * @note Array keys may not be consecutive after filtering.
	 * @param array $columns
	 * @param array $allowedColumns List of columns to allow. [] for all columns.
	 * @return array The list of columns after filtering is applied
	 */
	private function filterColumns( array $columns, array $allowedColumns ) {
		if ( count( $allowedColumns ) === 0 ) {
			return $columns;
		}

		$finalColumns = [];
		foreach ( $columns as $colId => $col ) {
			if ( in_array( $col->label, $allowedColumns ) ) {
				$finalColumns[$colId] = $col;
			}
		}
		return $finalColumns;
	}

	/**
	 * Sort an array pseudo-randomly using an affine transform
	 *
	 * @param array &$items Stuff to sort (sorted in-place)
	 * @return array
	 */
	private function sortRandomly( &$items ) {
		$totItems = count( $items );
		// No point in randomizing if only one item
		if ( count( $items ) > 1 ) {
			$rand1 = mt_rand( 1, $totItems - 1 );
			$rand2 = mt_rand( 0, $totItems - 1 );

			while ( $rand1 < $totItems - 1 && $rand1 % $totItems === 0 ) {
				// Make rand1 relatively prime to $totItems.
				$rand1++;
			}
			uksort( $items, static function ( $a, $b ) use( $rand1, $rand2, $totItems ) {
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
	 * @param array $tagSpecifier What tags to check (aka $options['tags'])
	 * @param array $itemTags What tags is this item tagged with
	 * @return bool If the item matches
	 */
	private function matchesTag( array $tagSpecifier, array $itemTags ) {
		if ( !$tagSpecifier ) {
			// We want the empty case to be considered a match.
			return true;
		}
		// We first want to find if there exists a group that matches.
		$matchesOneGroup = false;
		foreach ( $tagSpecifier as $tagGroups ) {
			// Inside the group, we want to verify for all group
			// members, the group matches
			$matchesAllAlternatives = true;
			foreach ( $tagGroups as $tagAlt ) {
				if ( !in_array( $tagAlt, $itemTags ) ) {
					$matchesAllAlternatives = false;
					break;
				}
			}
			if ( $matchesAllAlternatives ) {
				$matchesOneGroup = true;
				break;
			}
		}
		return $matchesOneGroup;
	}

	/**
	 * Convert JSON to markup that's easier for humans.
	 * @return string
	 */
	public function convertToHumanEditable() {
		$this->decode();
		return CollaborationKitSerialization::getSerialization( [
			$this->description,
			$this->getHumanOptions(),
			$this->getHumanEditableList()
		] );
	}

	/**
	 * Output the options in human readable form
	 *
	 * @return string key=value pairs separated by newlines.
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
	 * @return string
	 * @todo i18n-ize
	 */
	public function getHumanEditableList() {
		$this->decode();

		$out = '';
		foreach ( $this->columns as $column ) {
			// Use two to separate columns
			$out .= self::HUMAN_COLUMN_SPLIT;
			if ( isset( $column->label ) ) {
				$out .= CollaborationHubContent::escapeForHumanEditable( $column->label );
			} else {
				$out .= 'column';
			}
			if ( isset( $column->notes ) ) {
				$out .= '|notes='
					. CollaborationHubContent::escapeForHumanEditable( $column->notes );
			}
			$out .= self::HUMAN_COLUMN_SPLIT2;

			foreach ( $column->items as $item ) {
				$out .= CollaborationHubContent::escapeForHumanEditable( $item->title );
				if ( isset( $item->notes ) ) {
					$out .= '|'
					. CollaborationHubContent::escapeForHumanEditable( $item->notes );
				} else {
					$out .= '|';
				}
				if ( isset( $item->link ) ) {
					if ( $item->link === false ) {
						$out .= '|nolink';
					} else {
						$out .= "|link="
							. CollaborationHubContent::escapeForHumanEditable( $item->link );
					}
				}
				if ( isset( $item->image ) ) {
					if ( $item->image === false ) {
						$out .= '|noimage';
					} else {
						$out .= '|image='
							. CollaborationHubContent::escapeForHumanEditable( $item->image );
					}
				}
				if ( isset( $item->tags ) ) {
					foreach ( (array)$item->tags as $tag ) {
						$out .= '|tag='
							. CollaborationHubContent::escapeForHumanEditable( $tag );
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
	 * @param string $options Human readable options
	 * @return stdClass
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
	 * @param string $text text to convert
	 * @return array Result of converting it to native form
	 * @throws MWContentSerializationException
	 */
	public static function convertFromHumanEditable( $text ) {
		$res = [ 'columns' => [] ];

		$split2 = strrpos(
			$text,
			CollaborationKitSerialization::SERIALIZATION_SPLIT
		);
		if ( $split2 === false ) {
			throw new MWContentSerializationException( 'Missing list description' );
		}

		$split1 = strrpos(
			$text, CollaborationKitSerialization::SERIALIZATION_SPLIT,
			-strlen( $text ) + $split2 - 1
		);
		if ( $split1 === false ) {
			throw new MWContentSerializationException( 'Missing list description' );
		}
		$dividerLength = strlen( CollaborationKitSerialization::SERIALIZATION_SPLIT );

		$optionLength = $split2 - ( $split1 + $dividerLength );
		$optionString = substr( $text, $split1 + $dividerLength, $optionLength );
		$res['options'] = self::parseHumanOptions( $optionString );

		if ( isset( $res['options']->DISPLAYMODE ) ) {
			$res['displaymode'] = $res['options']->DISPLAYMODE;
			unset( $res['options']->DISPLAYMODE );
		} else {
			throw new MWContentSerializationException( 'Missing list displaymode' );
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

	/**
	 * @param string $column
	 * @return array
	 * @throws MWContentSerializationException
	 */
	private static function convertFromHumanEditableColumn( $column ) {
		// Adding newline so that HUMAN_COLUMN_SPLIT2 correctly triggers
		$column .= "\n";

		$columnItem = [ 'items' => [] ];

		$columnContent = explode( self::HUMAN_COLUMN_SPLIT2, $column );

		$parts = explode( '|', $columnContent[0] );

		$parts = array_map(
			[ 'CollaborationHubContent', 'unescapeForHumanEditable' ],
			$parts
		);

		if ( $parts[0] != 'column' ) {
			$columnItem['label'] = $parts[0];
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
							'Unrecognized option for column:' .
							wfEscapeWikiText( $key )
						);
				}
			}
		}

		if ( count( $columnContent ) == 1 ) {
			return $columnItem;
		} else {
			$listLines = explode( "\n", $columnContent[1] );
			foreach ( $listLines as $line ) {
				// Skip empty lines
				if ( trim( $line ) !== '' ) {
					$columnItem['items'][] = self::convertFromHumanEditableItemLine( $line );
				}
			}
		}

		return $columnItem;
	}

	/**
	 * @param string $line
	 * @return array
	 * @throws MWContentSerializationException
	 */
	private static function convertFromHumanEditableItemLine( $line ) {
		$parts = explode( '|', $line );
		$parts = array_map(
			[ 'CollaborationHubContent', 'unescapeForHumanEditable' ],
			$parts
		);
		$itemRes = [ 'title' => $parts[0] ];
		if ( count( $parts ) > 1 ) {
			// If people are using batch editor, they might define an image etc.
			// despite lack of a note. This is to catch that and prevent weirdness.
			$testExplosion = explode( '=', $parts[1] );
			if ( in_array( $testExplosion[0], [ 'image', 'link', 'tags', 'sortkey' ] ) ) {
				$itemRes[$testExplosion[0]] = $testExplosion[1];
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
							'Unrecognized option for list item:' .
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
	 *
	 * @param Parser $parser
	 * @param string $pageName
	 * @param string ...$args
	 * @return string|array HTML string or an array [ string, 'noparse' => false ]
	 */
	public static function transcludeHook( $parser, $pageName = '', ...$args ) {
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
			} elseif ( $name === 'column' ) {
				$options['columns'][] = $value;
			} elseif ( $name === 'columns' ) {
				// No-op. The preferred parameter name is the singular "column"
				// but "columns" is still a valid option name. But without special
				// handling, it causes an exception since all of these parameters
				// are coming in as strings even though "columns" is an array.
				continue;
			} elseif (
				$name === 'offset' ||
				$name === 'iconWidth'
			) {
				$options[$name] = (int)$value;
			} elseif (
				$name === 'includeDesc' ||
				$name === 'showColumnHeaders'
			) {
				// (bool)'false' evaluates to true? What a country!
				if (
					$value === 'false' ||
					$value === 'no' ||
					$value === 0
				) {
					$options[$name] = false;
				} else {
					$options[$name] = (bool)$value;
				}
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

		$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
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
		$parser->getOutput()->addTemplate(
			$title,
			$wikipage->getId(),
			$wikipage->getLatest()
		);
		$res = $content->convertToWikitext( $lang, $options );
		return [ $res, 'noparse' => false ];
	}

	/**
	 * Sort users into active/inactive column
	 *
	 * @param stdClass $column An object representing the one column containing
	 *  the list of members of a given project. The object contains an attribute
	 *  "items" with a value of an array of objects representing individual list
	 *  items. Each of these has a key named title which contains a user name
	 *  (including namespace). May have non-users too.
	 * @return array Two column structure sorted active/inactive.
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
				$userItems[$title->getText()] = $item;
			}
		}
		$res = $this->filterActiveUsers( $userItems );
		$inactiveFlatList = array_merge(
			array_values( $res['inactive'] ),
			$nonUserItems
		);

		// So currently, columns can be selected based on their names,
		// which is based on a Message object, which is not the nicest
		// for the autogenerated active/inactive columns.
		$activeColumn = (object)[
			'items' => array_values( $res['active'] ),
			'label' => wfMessage( 'collaborationkit-column-active' )
				->inContentLanguage()
				->plain(),
		];
		$inactiveColumn = (object)[
			'items' => $inactiveFlatList,
			'label' => wfMessage( 'collaborationkit-column-inactive' )
				->inContentLanguage()
				->plain(),
		];

		return [ $activeColumn, $inactiveColumn ];
	}

	/**
	 * Filter users into active and inactive.
	 *
	 * @note The results of this function get stored in parser cache.
	 * @param array $userList Array of usernames => stdClass
	 * @return array [ 'active' => [..], 'inactive' => '[..]' ]
	 */
	private function filterActiveUsers( $userList ) {
		if ( count( $userList ) > 0 ) {
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
		} else {
			return [ 'active' => [], 'inactive' => [] ];
		}
	}

	/**
	 * Hack to allow styles to be loaded from plain transclusion.
	 *
	 * We don't have access to a parser object in getWikitextForTransclusion().
	 * So instead we put <collaborationkitloadliststyles/> on the page, which
	 * calls this.
	 *
	 * @param string $content Input to parser hook
	 * @param array $attributes
	 * @param Parser $parser
	 * @return string Empty string
	 */
	public static function loadStyles( $content, array $attributes, Parser $parser ) {
		$parser->getOutput()->addModuleStyles( [
			'ext.CollaborationKit.list.styles',
			'ext.CollaborationKit.icons'
		] );
		return '';
	}

	/**
	 * Hook used to determine if current user should be given the edit interface
	 * for a page.
	 *
	 * @todo Not clear if this is the best hook to use. onBeforePageDisplay
	 *  doesn't have easy access to oldid
	 *
	 * @param Article $article
	 */
	public static function onArticleViewHeader( Article $article ) {
		$title = $article->getTitle();
		$context = $article->getContext();
		$user = $context->getUser();
		$output = $context->getOutput();
		$action = $context->getRequest()->getVal( 'action', 'view' );
		$permissionManager = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();

		// @todo Does not trigger on perma-link to current revision
		// not sure if that's a desired behaviour or not.
		if ( $title->getContentModel() === __CLASS__
			&& $action === 'view'
			&& $title->getArticleID() !== 0
			&& $article->getOldID() === 0 /* current rev */
			&& $permissionManager->userCan( 'edit', $user, $title )
		) {
			$output->addJsConfigVars( 'wgEnableCollaborationKitListEdit', true );

			// FIXME: only load .list.members if the list is a member list
			// (displaymode = members)
			$output->addModules( [
				'ext.CollaborationKit.list.ui',
				'ext.CollaborationKit.list.members'
			] );
			$output->preventClickjacking();
		}
	}

	/**
	 * Hook to add timestamp for edit conflict detection
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage $out, $skin ) {
		// Used for edit conflict detection in lists.
		$revTS = (int)$out->getRevisionTimestamp();
		$out->addJsConfigVars( 'wgCollabkitLastEdit', $revTS );
	}

	/**
	 * Hook to use custom edit page for lists
	 *
	 * @param Article|Page $page
	 * @param User $user (Not used)
	 * @return bool|null
	 */
	public static function onCustomEditor( Page $page, User $user ) {
		if (
			$page instanceof Article
			&& $page->getPage()->getContentModel() === __CLASS__
		) {
			$editor = new CollaborationListContentEditor( $page );
			$editor->setContextTitle( $page->getTitle() );
			$editor->edit();
			return false;
		}
	}
}
