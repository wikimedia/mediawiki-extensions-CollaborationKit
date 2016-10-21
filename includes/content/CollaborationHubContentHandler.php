<?php

class CollaborationHubContentHandler extends TextContentHandler {

	const FORMAT_WIKI = 'text/x-collabkit';

	public function __construct(
		$modelId = 'CollaborationHubContent',
		$formats = [ CONTENT_FORMAT_JSON, CONTENT_FORMAT_TEXT, self::FORMAT_WIKI ]
	) {
		// text/x-collabkit is a format for lists similar to <gallery>.
		// CONTENT_FORMAT_TEXT is for back-compat with old revs. Could be removed.

		// @todo Ideally, we'd have the preferred format for editing be self::FORMAT_WIKI
		// and the preferred format for db be CONTENT_FORMAT_JSON. Unclear if that's
		// possible.
		parent::__construct( $modelId, $formats );
	}

	/**
	 * @param $title Title of page to check
	 * @return bool
	 */
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
	 * Constructs a CollaborationHubContent object. Does not perform any validation,
	 * as that is done at a later step (to allow for outputting of invalid content for
	 * debugging purposes.)
	 *
	 * @param $text string
	 * @param $format string|null
	 * @return CollaborationHubContent
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		if ( $format === self::FORMAT_WIKI ) {
			$data = CollaborationHubContent::convertFromHumanEditable( $text );
			$text = FormatJson::encode( $data );
		}
		$content = new CollaborationHubContent( $text );
		// Deliberately not validating at this step; validation is done later.
		return $content;
	}

	/**
	 * Serializes the CollaborationHubContent object.
	 *
	 * @param $content Content
	 * @param $format string|null
	 * @return mixed
	 */
	public function serializeContent( Content $content, $format = null ) {
		if ( $format === self::FORMAT_WIKI ) {
			return $content->convertToHumanEditable();
		}
		return parent::serializeContent( $content, $format );
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
	 * @param $title Title
	 * @param $displayName string
	 * @param $icon string
	 * @param $colour string
	 * @param $introduction string
	 * @param $footer string
	 * @param $content array
	 * @param $summary string Message key for edit summary
	 * @param $context IContextSource The calling context
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
