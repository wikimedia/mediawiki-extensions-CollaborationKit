<?php

class CollaborationHubContentHandler extends TextContentHandler {

	public function __construct( $modelId = 'CollaborationHubContent' ) {
		parent::__construct( $modelId );
	}

	public function canBeUsedOn( Title $title ) {
		global $wgCollaborationHubAllowedNamespaces;

		$namespace = $title->getNamespace();
		if ( in_array( $namespace, array_keys( array_filter( $wgCollaborationHubAllowedNamespaces ) ) )
			&& MWNamespace::hasSubpages( $namespace ) ) {

			return true;
		}
		return false;
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
		return new CollaborationHubContent( '{ "display_name": "", "introduction": "", "content": [] }' );
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
	 * @param string $displayName
	 * @param string $icon
	 * @param string $colour
	 * @param string $introduction
	 * @param string $footer
	 * @param array $content
	 * @param string $summary Message key for edit summary
	 * @param IContextSource $context The calling context
	 * @return Status
	 */
	public static function edit( Title $title, $displayName, $image, $colour, $introduction, $footer, $content, $summary, IContextSource $context ) {
		$contentBlock = [
			'display_name' => $displayName,
			'introduction' => $introduction,
			'footer' => $footer,
			'image' => $image,
			'colour' => $colour,
			'content' => $content
		];

		// TODO Do content

		$jsonText = FormatJson::encode( $contentBlock );
		if ( $jsonText === null ) {
			return Status::newFatal( 'collaborationkit-hub-edit-tojsonerror' );
		}

		// Ensure that a valid context is provided to the API in unit tests
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $title->getFullText(),
				'contentmodel' => 'CollaborationHubContent',
				'text' => $jsonText,
				'summary' => $summary,
				'token' => $context->getUser()->getEditToken(),
			],
			true // Treat data as POSTed
		);
		$der->setRequest( $request );

		try {
			$api = new ApiMain( $der, true );
			$api->execute();
		} catch ( UsageException $e ) {
			return Status::newFatal( $context->msg( 'collaborationkit-hub-edit-apierror',
				$e->getCodeString() ) );
		}
		return Status::newGood();
	}
}
