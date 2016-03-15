<?php

/**
 * Represents the content of a JSON Schema article...?
 *
 */
class CollaborationHubContent extends JsonContent {

	/*
	 * There would be proper documentation here, but there are too many of these to keep track of.
	 * All of these are strings unless otherwise noted.
	 */
	protected $hubID; // Some sort of unique id for the hub; shared between all pages in the hub
	protected $hubType; // Intended to be selectable from an on-wiki managed list of options
	protected $hubName;
	protected $hubScope; // (array) array of includes and excludes

	protected $isHubHub; // (bool) Whether or not it's the main page of the hub
	protected $pageName; // Only used by subpages, not hubhubs
	protected $description;
	protected $content; // (array or string)
	protected $contentType;

	/**
	 * Whether contents have been populated
	 * @var bool
	 */
	protected $decoded = false;

	/**
	 * @return string|null
	 */
	public function getHubID() {
		$this->decode();
		return $this->hubID;
	}

	/**
	 * @return string|null
	 */
	public function getHubName() {
		$this->decode();
		return $this->hubName;
	}

	/**
	 * @return array
	 */
	public function getHubScope() {
		$this->decode();
		return $this->hubScope;
	}

	/**
	 * @return boolean
	 */
	public function isHubHub() {
		$this->decode();
		return $this->isHubHub;
	}

	/**
	 * @return string|null
	 */
	public function getPageName() {
		$this->decode();
		return $this->pageName;
	}

	/**
	 * @return string|null
	 */
	public function getDescription() {
		$this->decode();
		return $this->description;
	}

	/**
	 * @return array|null
	 */
	public function getContent() {
		$this->decode();
		return $this->content;
	}

	/**
	 * @return array|null
	 */
	public function getContentType() {
		$this->decode();
		return $this->contentType;
	}

	function __construct( $text ) {
		parent::__construct( $text, 'CollaborationHubContent' );
	}

	/**
	 * Decodes the JSON schema into a PHP associative array.
	 * @return array: Schema array.
	 */
	function getJsonData() {
		return FormatJson::decode( $this->getNativeData() );
	}

	/**
	 * Decode and validate the contents.
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		$this->decode(); // Populate $this->stuff stuff.

		// Required fields
		if (
			!is_string( $this->getHubID() ) ||
			!is_string( $this->getDescription() )
		) {
			return false;
		}
		// HubHub fields
		if ( $this->isHubHub() === true ) {
			if (
				!is_string( $this->getHubName() ) ||
				!is_array( $this->getHubScope() )
			) {
				return false;
			}
		} else {
			// HubPage fields
			if (
				!is_string( $this->getPageName() )
			) {
				return false;
			}
		}

		// Content needs to either be wikitext or a sensible array.
		if ( !is_string( $this->getContent() ) && !is_array( $this->getContent() ) ) {
			return false;
		}
		if ( is_array( $this->getContent() ) ) {
			$content = $this->getContent();
			foreach ( $content as $contentItem ) {
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
			$this->hubID = isset( $data->hub_id ) ? $data->hub_id : null;
			$this->hubType = isset( $data->hub_type ) ? $data->hub_type : null;
			$this->hubName = isset( $data->hub_name ) ? $data->hub_name : null;
			$this->pageName = isset( $data->page_name ) ? $data->page_name : null;
			$this->description = isset( $data->description ) ? $data->description : '';
			$this->isHubHub = isset( $data->hub_hub ) ? $data->hub_hub : false;

			if ( isset( $data->content ) && is_object( $data->content ) ) {

				$validTypes = array( 'subpage-list', 'icon-list', 'block-list', 'list' );
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

			$this->hubScope = array();
			if ( isset( $data->hub_scope ) && is_object( $data->hub_scope ) ) {

				// OMG it actually contains stuff; TODO do actual stuff to deal with it
			}
		}
		$this->decoded = true;
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

		// Parse the output text.
		$output = $wgParser->parse( $this->getDescription(), $title, $options, true, true, $revId );

		if ( !$this->isHubHub() ) {
			// generate hub subpage header stuff
			$output->setText( '((subpage links/header etc))' . $output->getText() );
		} else {
			// TODO generate special hubhub intro layout
		}

		$output->setText( $output->getText() . $this->getParsedContent( $title, $revId, $options, $output ) );

		// TODO other bits
	}


	/**
	 * Helper function for generateList... why?
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param ParserOutput $output
	 * @return string
	 */
	protected function getParsedDescription( Title $title, $revId, ParserOptions $options,
		ParserOutput &$output
	) {
		global $wgParser;
		$placeHolderOutput = $wgParser->parse( $this->getDescription(), $title, $options, true, true, $revId );
		return $placeHolderOutput->getText();
	}


	/**
	 * Helper function for fillParserOutput; return chunks of parsed output based on $content
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param ParserOutput $output
	 * @return string
	 */
	protected function getParsedContent( Title $title, $revId, ParserOptions $options,
		ParserOutput &$output
	) {
		global $wgParser;
		if ( $this->getContentType() == 'wikitext' ) {
			$placeHolderOutput = $wgParser->parse( $this->getContent(), $title, $options );
			return $placeHolderOutput->getText();
		} else { // it's some kind of list
			return $this->generateList( $title, $revId, $options, $output );
		}
	}


	/**
	 * Helper function for fillParserOutput; return HTML for displaying lists.
	 * This will be more specific to type later.
	 * @param string $type
	 * @return string
	 */
	protected function generateList( Title $title, $revId, ParserOptions $options,
		ParserOutput &$output
	) {
		$html = '';
		$content = $this->getContent();
		$type = $this->getContentType();

		if ( $type == 'subpage-list' ) {
			$ToC = Html::element( 'p', array(), 'TOC magically appears here later' );
			$list = '';

			foreach ( $content as $item ) {
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
				$list .= $spContent->getParsedDescription( $title, $revId, $options, $output );
				// TODO wrap in stuff; limit number of things to output for lists, length for wikitext
				$list .= $spContent->getParsedContent( $title, $revId, $options, $output );

				$list .= Html::closeElement( 'div' );

				// Register page as dependency
				// $parserOutput->addTemplate( $title, $title->getArticleId(), $rev->getId() );
			}
			$html .= $ToC . $list;
		} else {
			// TODO redo this entire thing
			$html .= Html::openElement( 'ul' );

			foreach ( $content as $item ) {
				$html .= Html::openElement( 'li' );
				$html .= Html::rawElement( 'span', array( 'class' => 'doink' ), $item['item'] );
				$html .= Html::closeElement( 'li' );
			}
			$html .= Html::closeElement( 'ul' );
		}

		return $html;
	}
}
