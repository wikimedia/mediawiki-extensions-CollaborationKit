<?php

/**
 * Represents the content of a JSON Schema article...?
 *
 */
class CollaborationHubContent extends JsonContent {

	/**
	 * Page type; used for special handling for defined types
	 * Must be one of $availablePageTypes
	 * @var string
	 */
	protected $pageType;

	/**
	 * default: no special type defined
	 * main: hub main page; sometimes called a hubhub
	 * userlist: list of members
	 * others may later include scope, announcements, related projects, etc
	 * @var array
	 */
	protected $availablePageTypes = array(
		'default',
		'main',
		'userlist'
	);

	/**
	 * Page displayname, used for headers in the hub main page and stuff; doubles as project name on pagetype main
	 * @var string|null
	 */
	protected $pageName;

	/**
	 * Page icon, used for toc and other stuff
	 * Need to refer to a canned icon in the set or a file on-wiki; no icon results in a random one
	 * @var string|null
	 */
	protected $icon;

	/**
	 * Page description/intro wikitext
	 * @var string
	 */
	protected $description;

	/**
	 * Array or string of page content
	 * @var array|string
	 */
	protected $content;

	/**
	 * Type of content: most be one of $availableContentTypes
	 * @var string
	 */
	protected $contentType;

	/**
	 * @var array
	 */
	protected $availableContentTypes = array(
		'wikitext',
		'subpage-list',
		'icon-list',
		'block-list',
		'list'
	);

	/**
	 * Whether contents have been populated
	 * @var bool
	 */
	protected $decoded = false;

	function __construct( $text ) {
		parent::__construct( $text, 'CollaborationHubContent' );
	}

	/**
	 * Decode and validate the contents.
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		$this->decode();
		// Required fields
		if (
			!is_string( $this->description ) ||
			!is_string( $this->pageType ) ||
			!is_string( $this->pageName )
		) {
			return false;
		}

		// Optional icon
		if ( isset( $this->icon ) && !is_string( $this->icon ) ) {
			return false;
		}

		// Check page type and content type for being available
		if (
			!in_array( $this->pageType, $this->availablePageTypes ) ||
			!in_array( $this->contentType, $this->availableContentTypes )
		) {
			return false;
		}

		// 'content' needs to either be wikitext or a sensible array for formatting.
		if ( !is_string( $this->content ) && !is_array( $this->content ) ) {
			return false;
		}
		if ( is_array( $this->content ) ) {
			foreach ( $this->content as $contentItem ) {
				// 'item' is required; 'icon' and 'notes' are optional
				if (
					!is_string( $contentItem['item'] ) ||
					( !is_string( $contentItem['icon'] ) && $contentItem['icon'] !== null ) ||
					( !is_string( $contentItem['notes'] ) && $contentItem['notes'] !== null )
				) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Decode the JSON contents and populate protected variables.
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return;
		}
		$jsonParse = $this->getData();
		$data = $jsonParse->isGood() ? $jsonParse->getValue() : null;
		if ( $data ) {
			$this->pageName = isset( $data->page_name ) ? $data->page_name : null;
			$this->description = isset( $data->description ) ? $data->description : '';
			$this->icon = isset( $data->icon ) ? $data->icon : '';
			$this->pageType = isset( $data->page_type ) ? $data->page_type : 'default';

			if ( isset( $data->content ) && is_object( $data->content ) ) {
				if (
					isset( $data->content->type ) &&
					in_array( $data->content->type, $this->availableContentTypes ) &&
					isset( $data->content->items ) &&
					is_array( $data->content->items )
				) {
					$this->content = array();
					$this->contentType = $data->content->type;

					// parse them all the same way; we don't care about missing/extra stuff
					$this->content = array();
					foreach ( $data->content->items as $itemObject ) {
						if ( !is_object( $itemObject ) ) { // Malformed item
							$this->content = null;
							break;
						}
						$item = array();
						$item['item'] = isset( $itemObject->item ) ? $itemObject->item : null;
						$item['icon'] = isset( $itemObject->icon ) ? $itemObject->icon : null;
						$item['notes'] = isset( $itemObject->notes ) ? $itemObject->notes : null;

						$this->content[] = $item;
					}
				} else {
					// Not a valid type, content is malformed
					$this->content = null;
					echo 'content type weird';
				}
			} else {
				$this->contentType = 'wikitext';
				$this->content = isset( $data->content ) ? $data->content : null;
			}
		}
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
	 * @return string
	 */
	public function getIcon() {
		$this->decode();
		return $this->icon;
	}

	/**
	 * @return string
	 */
	public function getContent() {
		$this->decode();
		return $this->content;
	}

	/**
	 * @return string
	 */
	public function getPageType() {
		$this->decode();
		return $this->pageType;
	}

	/**
	 * @return string|null
	 */
	public function getPageName() {
		$this->decode();
		return $this->pageName;
	}

	/**
	 * @return array|null
	 */
	public function getContentType() {
		$this->decode();
		return $this->contentType;
	}

	/**
	 * @return array
	 */
	public function getPossibleTypes() {
		$this->decode();
		return $this->availableContentTypes;

		// Will include some generic canned stuff later, but doesn't currently.
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
		$output->setText( Html::rawElement(
			'div',
			array( 'class' => 'wp-intro' ),
			$this->getParsedDescription( $title, $options )
		) );

		if ( $this->getPageType() == 'main' ) {

			// Image
			$prependiture = Html::rawElement(
				'div',
				array( 'id' => 'wp-header-icon', 'class' => 'wp-junk' ),
				$this->getImage( 'none', 120 )
			);

			// Members
			$membersTitle = Title::newFromText( $title->getFullText() . '/' . wfmessage( 'collaborationkit-members-header' ) );
			if ( isset( $membersTitle ) ) {
				$prependiture .= Html::openElement(
					'div',
					array( 'id' => 'wp-header-members', 'class' => 'toc wp-junk' )
				);
				$prependiture .= Html::element(
					'h2',
					[],
					wfmessage( 'collaborationkit-members-header' )
				);
				$prependiture .= Html::rawElement(
					'div',
					[],
					Revision::newFromTitle( $membersTitle )->getContent()->generateList( $title, $options )
				);
				$prependiture .= Html::closeElement( 'div' );
			}

			$output->setText(
				Html::openElement(
					'div',
					array( 'class' => 'wp-mainpage wp-collaborationhub' )
				) . $prependiture . $output->getText()
			);
		} else {
			// generate hub subpage header stuff
			$parent = $this->getParentHub( $title );
			if ( $parent !== null ) {
				$toc = $this->generateToC( $parent, 'secondary' );
			} else {
				$toc = '';
			}

			$output->setText(
				Html::openElement(
					'div',
					array( 'class' => 'wp-subpage wp-collaborationhub' )
				) . $toc . $output->getText()
			);

			// specific types

		}

		$output->addModules( 'ext.CollaborationKit.main' );
		$output->setText( $output->getText() . $this->getParsedContent( $title, $options ) );
		// TODO other bits

		$output->setText( $output->getText() . Html::closeElement( 'div' ) );
	}

	/**
	 * Helper function for fillParserOutput
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getParsedDescription( Title $title, ParserOptions $options ) {
		global $wgParser;
		$placeHolderOutput = $wgParser->parse( $this->getDescription(), $title, $options );
		return $placeHolderOutput->getText();
	}

	/**
	 * Helper function for fillParserOutput; return chunks of parsed output based on $content
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getParsedContent( Title $title, ParserOptions $options ) {
		global $wgParser;
		if ( $this->getContentType() == 'wikitext' ) {
			$placeHolderOutput = $wgParser->parse( $this->getContent(), $title, $options );
			return $placeHolderOutput->getText();
		} else { // it's some kind of list
			return $this->generateList( $title, $options );
		}
	}


	/**
	 * Helper function for fillParserOutput; return HTML for displaying lists.
	 * This will be more specific to type later.
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param string $type EXCEPT IT'S NOT HERE EVEN THOUGH IT SHOULD BE HERE YOU MORON TODO STOP BEING A MORON
	 * @return string
	 */
	protected function generateList( Title $title, ParserOptions $options ) {
		global $wgParser;
		$html = '';

		if ( $this->getContentType() == 'subpage-list' ) {
			$ToC = $this->generateToC( $title );
			$list = '';

			foreach ( $this->getContent() as $item ) {

				// TODO check if subpage exists

				// get collaborationhubcontent object for the subpage and stuff
				$spTitle = Title::newFromText( $item['item'] );
				$spRev = Revision::newFromTitle( $spTitle );
				$list .= Html::openElement( 'div', array( 'class' => 'wp-pagelist-section' ) );

				// So the ToC has something to link to
				$tocLinks = array();

				if ( isset( $spRev ) ) {
					$spContent = $spRev->getContent();
					$spContentModel = $spRev->getContentModel();
					// TODO Check if it's even a hub?

					if ( $spContentModel == 'CollaborationHubContent' ) {
						$spPage = $spContent->getPageName();
					} else {
						$spPage = $spTitle->getSubpageText();
					}
				} else {
					$spPage = $spTitle->getSubpageText();
				}
				$spPageLink = Sanitizer::escapeId( htmlspecialchars( $spPage ) );

				// Replicate generateToC's handling of duplicates
				while ( in_array( $spPageLink, $tocLinks ) ) {
					$spPageLink .= '1';
				}
				$tocLinks[] = $spPageLink;

				if ( isset( $spRev ) ) {
					// add content block to listContent
					// TODO sanitise, add anchor for toc
					$list .= Html::element(
						'h2',
						array( 'id' => $spPageLink ),
						$spPage
					);

					// TODO wrap in stuff
					// TODO REPLACE ALL THIS WITH PROPER AGNOSTIC HANDLING SOMEHOW
					if ( $spContentModel == 'CollaborationHubContent' ) {
						// TODO wrap in stuff
						$list .= $spContent->getParsedDescription( $title, $options );
						// TODO wrap in stuff; limit number of things to output for lists, length for wikitext
						$list .= $spContent->getParsedContent( $title, $options );
					} else {
						// Oh shit it's not a hubpage
						if ( $spContentModel == 'wikitext' ) {
							$list .= $spContent->getParserOutput( $spTitle )->getText();
						} else {
							// Oh shit, what?
						}
					}
				} else {
					// TODO Replace this with a button to special:createcollaborationhub/title
					$list .= Html::rawElement(
						'h2',
						array( 'id' => $spPageLink ),
						Linker::link( $spTitle )
					);
				}
				$list .= Html::closeElement( 'div' );

				// Register page as dependency
				// $parserOutput->addTemplate( $title, $title->getArticleId(), $rev->getId() );
			}
			$html .= $ToC . $list;
		} else {
			// TODO redo this entire thing
			$html .= Html::openElement( 'ul' );

			foreach ( $this->getContent() as $item ) {
				// Let's just assume this is wikitext.
				$printNotes = isset( $item['notes'] ) ? $wgParser->parse( $item['notes'], $title, $options )->getText() : null;
				// I DON'T CARE. $item['icon'];
				$printIcon = null;
				// Special handling for members, otherwise just parse as wikitext
				if ( $this->getPageType() == 'userlist' ) {
					$userID = (int) $item['item'];
					// Check if the user id is even possibly valid
					if ( !is_int( $userID ) || $userID == 0 ) {
						continue;
					}
					$user = User::newFromId( $userID );
					$user->load(); // Update with real information so it doesn't just return the supplied id above
					if ( $user->getId() == 0 ) {
						// Nonexistent user
						continue;
					}
					$printItem = Linker::link( $user->getUserPage(), $user->getName() );
				} else {
					$printItem = $wgParser->parse( $item['item'], $title, $options )->getText();
				}

				$html .= Html::openElement( 'li' );
				$html .= Html::rawElement( 'span', array( 'class' => 'doink' ), $printItem );
				$html .= Html::closeElement( 'li' );
			}
			$html .= Html::closeElement( 'ul' );
		}

		return $html;
	}

	/**
	 * Helper function for fillParserOutput; return HTML for a ToC.
	 * @param Title $title for target
	 * @param string $type: main or flat or stuff (used as css class)
	 * @return string|null
	 */
	protected function generateToC( Title $title, $type = 'main' ) {
		// TODO use correct version of text; support PREVIEWS as well as just pulling the content revision
		$rev = Revision::newFromTitle( $title );
		if ( isset( $rev ) ) {
			$sourceContent = $rev->getContent();
			$html = '';

			if ( $rev->getContentModel() == 'CollaborationHubContent' ) {
				$ToCItems = array();
				foreach ( $sourceContent->getContent() as $item ) {
					$spTitle = Title::newFromText( $item['item'] );
					$spRev = Revision::newFromTitle( $spTitle );

					if ( isset( $spRev ) ) {
						$spContent = $spRev->getContent();
						$spContentModel = $spRev->getContentModel();
					} else {
						$spContentModel = 'none';
					}

					// Display name and #id
					$item = $spContentModel == 'CollaborationHubContent' ?
						$spContent->getPageName() : $spTitle->getSubpageText();
					$display = Html::element( 'span', [], $item );
					while ( isset( $ToCItems[$item] ) ) {
						// Already exists, add a 1 to the end to avoid duplicates
						$item = $item . '1';
					}

					// Link
					if ( $type != 'main' ) {
						// TODO add 'selected' class if already on it
						$link = $spTitle;
					} else {
						$link = Title::newFromText( '#' . htmlspecialchars( $item ) );
					}

					// Icon
					if ( $spContentModel == 'CollaborationHubContent' /* && icon is set in $spContent */ ) {
						$display = $spContent->getImage( 'random', 50 ) . $display;
					} else {
						// Use this one as a surrogate because it's not a real hub page; $link can act as seed
						$display = $this->getImage( 'random', 50, $item ) . $display;
					}

					$ToCItems[$item] = Linker::Link( $link, $display );
				}
				$html .= Html::openElement( 'div', array( 'class' => 'wp-toc' ) );
				if ( $type != 'main' ) {
					// TODO Make this proper
					$html .= Html::rawElement(
						'h3',
						array(),
						Linker::Link( $title, $sourceContent->getPageName() )
					);
				}
				$html .= Html::openElement( 'ul' );

				foreach ( $ToCItems as $item => $linkTitle ) {
					$html .= Html::rawElement(
						'li',
						array( 'class' => 'wp-toc-item' ),
						$linkTitle
					);
				}
				$html .= Html::closeElement( 'ul' );
				$html .= Html::closeElement( 'div' );
			} else {
				$html = 'Page not found, ToC not possible';
			}
		} else {
			$html = '';
		}

		return $html;
	}

	/**
	 * Helper function for fillParserOutput for tocs on subpages
	 * @param Title $title for target
	 * @return Title|null of first found mainpage pagelist hub; null if none
	 */
	protected function getParentHub( Title $title ) {
		$baseTitle = $title->getBaseTitle();

		if ( $title->equals( $baseTitle ) ) {
			return null;
		}

		// Keep looking
		while ( !$title->equals( $baseTitle ) ) {
			$title = $baseTitle;
			$baseTitle = $title->getBaseTitle();
			$baseRev = Revision::newFromTitle( $baseTitle );

			if (
				$baseTitle->getContentModel() == 'CollaborationHubContent' &&
				isset( $baseRev ) &&
				$baseRev->getContent()->getPageType() == 'main' &&
				$baseRev->getContent()->getContentType() == 'subpage-list'
			) {
				return $baseTitle;
			}
		}

		return null;
	}

	/**
	 * Helper function for generateToC and crap
	 * @param string $icon data from json; either an icon id or anything to use as a seed
	 * @param int $size TODO USE THIS SOMEHOW
	 * @return string html
	 */
	protected function makeIcon( $icon, $size ) {

		// Keep this synced with icons.svg and the less file(s)
		$iconsPreset = array(
			// Randomly selectable items
			'sections',
			'list',
			'newspaper',
			'checked',
			'eye',
			'page',
			'goodpage',
			'circlestar',
			'rocket',
			'die',
			'puzzlepiece',
			'star',
			'sun',
			'ribbon',

			// Less generic items
			'gear',
			'search',
			'book',
			'pencil',
			'mapmarker',
			'link',
			'flag',
			'bookmark',
			'translate',
			'play',
			'quote',
			'clock',
			'bell',
			'user',
			'heart',
			'discussion',
			'gallery',
			'lock',
			'nowikitext',
		);
		// TODO if it's an uploaded file (begins with 'file:' and/or ends with '.filextension'); use that as source and set class to 'user-upload' (wfFindFile( $icon ))
		// if preset or other logical class name, just set class; we allow non-preset ones for on-wiki flexibility?
		if ( $icon !== null && in_array( $icon, $iconsPreset ) ) {
			$class = Sanitizer::escapeClass( $icon );
		} else {
			// Choose random class name using $icon value as seed
			$class = $iconsPreset[ hexdec( sha1( $icon )[0] ) % 14 ];
		}

		return Html::element( 'div', array( 'class' => 'toc-icon ' . $class ) );
	}

	/**
	 * Helper function for fillParserOutput to actually generate an image out of the icon value
	 * @param string $fallback for what to do for no icons
	 * @param int $size image size in px
	 * @param string $seed fallback seed for non-chc pages called from another ch
	 * @return string html|HORRIBLE GAPING VOID
	 */
	public function getImage( $fallback = 'none', $size = 50, $seed = null ) {
		if ( $seed === null ) {
			$icon = $this->getIcon();

			if ( $icon == '' || $icon == '-' ) {
				if ( $fallback == 'none' ) {
					return '';
				} else {
					return $this->makeIcon( $this->getPageName(), $size );
				}
			}
			if ( wfFindFile( $icon ) ) {
				return wfFindFile( $icon )->transform( array( 'width' => $size ) )->toHtml();
			} else {
				return $this->makeIcon( $icon, $size );
			}
		} else {
			// No icon data etc; use seed
			return $this->makeIcon( $seed, $size );
		}

		// TODO make it handle/return error/do something besides just selecting a random one when file doesn't exist/icon key not found
	}
}
