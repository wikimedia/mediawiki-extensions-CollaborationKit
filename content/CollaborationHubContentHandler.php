<?php

class CollaborationHubContentHandler extends TextContentHandler {

	public function __construct( $modelId = 'CollaborationHubContent' ) {
		parent::__construct( $modelId );
	}

	/**
	 * @param string $text
	 * @param string $format
	 * @return CollaborationHubContent
	 * @throws MWContentSerializationException
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		$content = new CollaborationHubContent( $text );
		if ( !$content->isValid() ) {
			throw new MWContentSerializationException( 'The collaborationhub content is invalid.' );
		}
		return $content;
	}

	/**
	 * @return CollaborationHubContent
	 */
	public function makeEmptyContent() {
		return new CollaborationHubContent( '{"hub_id": "", "hub_hub": true, "hub_type": "",
			"hub_name": "", "hub_scope": [], "description": "", "content": "" }' );
	}
	// Nothing calls this as yet, but for future reference or something...
	public function makeEmptyContentPage() {
		return new CollaborationHubContent( '{ "hub_id": "", "description": "", "page_name": "",
			"content": ""}' );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'CollaborationHubContent';
	}

	/**
	 * @return string
	 *//*
	protected function getDiffEngineClass() {
		return 'CollaborationHubDiffEngine';
	}*/

	/**
	 * @return bool
	 */
	public function isParserCacheSupported() {
		return true;
	}
}


