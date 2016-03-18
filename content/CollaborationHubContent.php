<?php

/**
 * Represents the content of a JSON Schema article...?
 *
 */
class CollaborationHubContent extends JsonContent {

	/**
	 * Hub displayname, used inline and for related projects and the like
	 * @var string|null
	 */
	protected $hubName;

	/**
	 * Page type id; used for special handling for defined types
	 *    0: no special type defined
	 *    1: hubhub (main page)
	 *    2: members
	 *    others: scope, announcements, related projects, etc
	 * This may all get chucked later in favour of more generic handlers.
	 * @var int
	 */
	protected $pageType;

	/**
	 * Page displayname, used for headers in the hubhub and stuff
	 * @var string|null
	 */
	protected $pageName;

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
	 * Type of content: wikitext, various kinds of pre-structured lists, etc
	 * @var string
	 */
	protected $contentType;

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
			!is_int( $this->pageType )
		) {
			return false;
		}
		// HubHub fields
		if ( $this->getPageType() == 1 ) {
			if ( !is_string( $this->hubName ) ) {
				return false;
			}
		} else {
			// HubPage fields
			if ( !is_string( $this->pageName ) ) {
				return false;
			}
		}

		// Content needs to either be wikitext or a sensible array.
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
			$this->hubName = isset( $data->hub_name ) ? $data->hub_name : null;
			$this->pageName = isset( $data->page_name ) ? $data->page_name : null;
			$this->description = isset( $data->description ) ? $data->description : '';
			$this->pageType = isset( $data->page_type ) ? $data->page_type : 0;

			if ( isset( $data->content ) && is_object( $data->content ) ) {

				$validTypes = array( 'subpage-list', 'icon-list', 'block-list', 'list', 'list-list' );
				if (
					isset( $data->content->type ) &&
					in_array( $data->content->type, $validTypes ) &&
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
					echo 'hook';
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
	 * @return string|null
	 */
	public function getHubName() {
		$this->decode();
		return $this->hubName;
	}

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
	public function getContent() {
		$this->decode();
		return $this->content;
	}

	/**
	 * @return boolean
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
		$output->setText( $this->getParsedDescription( $title, $options ) );

		if ( $this->getPageType() == 1 ) {
			// TODO generate special hubhub intro layout
		} else {
			// generate hub subpage header stuff
			$output->setText( '((subpage links/header etc))' . $output->getText() );

			// specific types

		}

		$output->setText( $output->getText() . $this->getParsedContent( $title, $options ) );
		// TODO other bits
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
			$ToC = Html::element( 'p', array(), 'TOC magically appears here later' );
			$list = '';

			foreach ( $this->getContent() as $item ) {
				// TODO add link to ToC

				// TODO check if subpage exists, use /notation for subpages
				// get collaborationhubcontent object for the subpage and stuff
				$spTitle = Title::newFromText( $item['item'] );
				$spRev = Revision::newFromTitle( $spTitle );
				$spContent = $spRev->getContent();

				// add content block to listContent
				$list .= Html::openElement( 'div' );
				// TODO sanitise, add anchor for toc
				$list .= Html::element( 'h2', array(), $spContent->getPageName() );
				// TODO wrap in stuff, use short version?
				$list .= $spContent->getParsedDescription( $title, $options );
				// TODO wrap in stuff; limit number of things to output for lists, length for wikitext
				$list .= $spContent->getParsedContent( $title, $options );

				$list .= Html::closeElement( 'div' );

				// Register page as dependency
				// $parserOutput->addTemplate( $title, $title->getArticleId(), $rev->getId() );
			}
			$html .= $ToC . $list;
		} elseif ( $this->getContentType() == 'list-list' ) {
			// meta list of lists, will recall generateList etc with different... types? What?
			// will need to change how generateList checks/gets list types
		} else {
			// TODO redo this entire thing
			$html .= Html::openElement( 'ul' );

			foreach ( $this->getContent() as $item ) {
				// Let's just assume this is wikitext.
				$printNotes = isset( $item['notes'] ) ? $wgParser->parse( $item['notes'], $title, $options )->getText() : null;
				// I DON'T CARE. $item['icon'];
				$printIcon = null;
				// Special handling for members, otherwise just parse as wikitext
				if ( $this->getPageType() == 2 ) {
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
}
