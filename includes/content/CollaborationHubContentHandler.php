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
		return new CollaborationHubContent( '{ "page_name": "", "description": "", "content": "", "page_type": 0 }' );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'CollaborationHubContent';
	}

	/**
	 * @return string
	 */
	/*protected function getDiffEngineClass() {
		return 'CollaborationHubDiffEngine';
	}*/

	/**
	 * @return bool
	 */
	public function isParserCacheSupported() {
		return true;
	}

	/**
	 * Edit a Collaboration Hub via the edit API
	 * @param Title $title
	 * @param string pageName
	 * @param string pageType
	 * @param string $description
	 * @param array|string $content
	 * @param string $summary Message key for edit summary
	 * @param IContextSource $context The calling context
	 * @return Status
	 */
	public static function edit( Title $title, $pageName, $pageType, $contentType, $description, $content, $summary,
		IContextSource $context
	) {
		$contentBlock = array(
			'page_name' => $pageName,
			'description' => $description
		);
		if ( $contentType == 'wikitext' ) {
			$contentBlock['content'] = $content;
		} else {
			$contentBlock['content'] = array(
				'type' => $contentType,
				'items' => $content
			);
			$contentBlock['page_type'] = $pageType;
		}
		$jsonText = FormatJson::encode( $contentBlock );
		if ( $jsonText === null ) {
			return Status::newFatal( 'collaborationhub-edit-tojsonerror' );
		}

		// Ensure that a valid context is provided to the API in unit tests
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			array(
				'action' => 'edit',
				'title' => $title->getFullText(),
				'contentmodel' => 'CollaborationHubContent',
				'text' => $jsonText,
				'summary' => $summary,
				'token' => $context->getUser()->getEditToken(),
			),
			true // Treat data as POSTed
		);
		$der->setRequest( $request );

		try {
			$api = new ApiMain( $der, true );
			$api->execute();
		} catch ( UsageException $e ) {
			return Status::newFatal( $context->msg( 'collaborationhub-edit-apierror',
				$e->getCodeString() ) );
		}
		return Status::newGood();
	}
}


