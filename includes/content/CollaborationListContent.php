<?php

/**
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
 *		"sortcriteria": {
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
		$text = $this->convertToWikitext( $lang, true );
		$output = $wgParser->parse( $text, $title, $options, true, true, $revId );
	}

	/**
	 * Convert the JSON to wikitext.
	 *
	 * @param $lang Language The (content) language to render the page in.
	 * @param $includeDesc boolean Include the description
	 * @return string The wikitext
	 */
	private function convertToWikitext( Language $lang, $includeDesc = false, $maxItems = false ) {
		$this->decode();

		$text = "__NOEDITSECTION__\n__NOTOC__";
		if ( includeDesc ) {
			$text .= $this->getDescription() . "\n";
		}
		if ( count( $this->items ) === 0 ) {
			$text .= "<hr>\n" . wfMessage( 'collaborationkit-listempty' )
				->inLanguage( $lang )
				->plain() . "\n";
		}
		$curItem = 0;
		foreach ( $this->items as $item ) {
			$curItem++;
			if ( $maxItems !== false && $maxItems < $curItem ) {
				break;
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
						->plain() .
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
			$text = $this->convertToWikitext( $wgContLang, true );
			return ContentHandler::makeContent( $text, null, $toModel );
		} elseif ( $toModel === CONTENT_MODEL_JSON ) {
			return ContentHandler::makeContent( $this->getNativeData(), null, $toModel );
		}
		return parent::convert( $toModel, $lossy );
	}

	public function getWikitextForTransclusion() {
		global $wgContLang;
		// FIXME Unclear if we should really do this as a transclusion, or
		// introduce a parser function. Too bad we don't have access to
		// transclusion parameters from this interface w/o doing something
		// insane:(
		$text = $this->convertToWikitext( $wgContLang, false, 5 );
		return $text;
	}
}
