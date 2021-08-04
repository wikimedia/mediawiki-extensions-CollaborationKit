<?php

use MediaWiki\Content\Transform\PreSaveTransformParams;
use MediaWiki\MediaWikiServices;

/**
 * Content handler for CollaborationListContent.
 *
 * We extend TextContentHandler instead of JsonContentHandler since
 * we do not display this as JSON code except upon request.
 *
 * @file
 */
class CollaborationListContentHandler extends TextContentHandler {
	const FORMAT_WIKI = 'text/x-collabkit';

	/**
	 * @param string $modelId
	 * @param string[] $formats
	 */
	public function __construct(
		$modelId = 'CollaborationListContent',
		$formats = [ CONTENT_FORMAT_JSON, CONTENT_FORMAT_TEXT, self::FORMAT_WIKI ]
	) {
		// text/x-collabkit is a format for lists similar to <gallery>.
		// CONTENT_FORMAT_TEXT is for back-compat with old revs. Could be removed.
		// @todo Ideally, we'd have the preferred format for editing be
		// self::FORMAT_WIKI and the preferred format for db be
		// CONTENT_FORMAT_JSON. Unclear if that's possible.
		parent::__construct( $modelId, $formats );
	}

	/**
	 * Can this content handler be used on a given page?
	 *
	 * @param Title $title Page to check
	 * @return bool
	 */
	public function canBeUsedOn( Title $title ) {
		global $wgCollaborationListAllowedNamespaces;

		$namespace = $title->getNamespace();
		return isset( $wgCollaborationListAllowedNamespaces[$namespace] ) &&
			$wgCollaborationListAllowedNamespaces[$namespace];
	}

	/**
	 * Takes JSON string and creates a new CollaborationListContent object.
	 *
	 * Validation is intentionally not done at this step, as it is done later.
	 *
	 * @param string $text
	 * @param string|null $format
	 * @return CollaborationListContent
	 * @throws MWContentSerializationException
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		if ( $format === self::FORMAT_WIKI ) {
			$data = CollaborationListContent::convertFromHumanEditable( $text );
			$text = FormatJson::encode( $data );
		}
		$content = new CollaborationListContent( $text );
		return $content;
	}

	/**
	 * Serializes the CollaborationListContent object.
	 *
	 * @param Content|CollaborationListContent $content
	 * @param string|null $format
	 * @return string
	 */
	public function serializeContent( Content $content, $format = null ) {
		if ( $format === self::FORMAT_WIKI ) {
			return $content->convertToHumanEditable();
		}
		return parent::serializeContent( $content, $format );
	}

	/**
	 * @return CollaborationListContent
	 */
	public function makeEmptyContent() {
		$empty = <<<JSON
			{
				"displaymode": "normal",
				"columns": [
					{ "items": [] }
				],
				"options": {},
				"description": ""
			}
JSON;
		return new CollaborationListContent( $empty );
	}

	/**
	 * Spawns a new "members" list, using the project creator as initial member.
	 *
	 * @param string $username Username without "User:" prefix
	 * @param string $initialDescription The initial description of the list
	 * @return CollaborationListContent
	 */
	public static function makeMemberList( $username, $initialDescription ) {
		$linkToUserpage = Title::makeTitleSafe( NS_USER, $username )
			->getPrefixedText();
		$newMemberList = [
			'displaymode' => 'members',
			'columns' => [ [
				'items' => [ [
					'title' => $linkToUserpage
				] ]
			] ],
			'options' => [
				'mode' => 'normal'
			],
			'description' => $initialDescription
		];
		$newMemberListJson = FormatJson::encode(
			$newMemberList,
			"\t",
			FormatJson::ALL_OK
		);
		return new CollaborationListContent( $newMemberListJson );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'CollaborationListContent';
	}

	/**
	 * FIXME is this really true?
	 * @return bool
	 */
	public function isParserCacheSupported() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function supportsDirectApiEditing() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function supportsRedirects() {
		return true;
	}

	/**
	 * Turns CollaborationListContent page into redirect
	 *
	 * Note that wikitext redirects are created, as generally, this content model
	 * is used in namespaces that support wikitext, and wikitext redirects are
	 * expected.
	 *
	 * @param Title $destination The page to redirect to
	 * @param string $text Text to include in the redirect.
	 * @return Content
	 */
	public function makeRedirectContent( Title $destination, $text = '' ) {
		$handler = new WikitextContentHandler();
		return $handler->makeRedirectContent( $destination, $text );
	}

	/**
	 * Beautifies JSON and does subst: prior to save.
	 *
	 * @param Content $content
	 * @param PreSaveTransformParams $pstParams
	 * @return CollaborationListContent
	 */
	public function preSaveTransform( Content $content, PreSaveTransformParams $pstParams ): Content {
		'@phan-var CollaborationListContent $content';
		$parser = MediaWikiServices::getInstance()->getParser();
		// WikiPage::doEditContent invokes PST before validation. As such,
		// native data may be invalid (though PST result is discarded later in
		// that case).
		$text = $content->getText();
		// pst will hopefully not make json invalid. Def should not.
		$pst = $parser->preSaveTransform(
			$text,
			$pstParams->getPage(),
			$pstParams->getUser(),
			$pstParams->getParserOptions()
		);

		$pstContent = new CollaborationListContent( $pst );

		if ( !$pstContent->isValid() ) {
			return $content;
		}

		return new CollaborationListContent( $pstContent->beautifyJSON() );
	}

	/**
	 * Posts the newly created "members" list on-wiki.
	 *
	 * @param Title $title
	 * @param string $summary
	 * @param IContextSource $context
	 * @todo rework this to use a generic CollaborationList editor function once
	 *  it exists
	 * @return Status
	 */
	public static function postMemberList( Title $title, $summary,
		IContextSource $context
	) {
		$username = $context->getUser()->getName();
		$collabList = self::makeMemberList(
			$username,
			$context->msg( 'collaborationkit-hub-members-description' )->text()
		);
		// Ensure that a valid context is provided to the API in unit tests
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $title->getFullText(),
				'contentmodel' => 'CollaborationListContent',
				'contentformat' => 'application/json',
				'text' => $collabList->serialize(),
				'summary' => $summary,
				'token' => $context->getUser()->getEditToken(),
			],
			true // Treat data as POSTed
		);
		$der->setRequest( $request );
		try {
			$api = new ApiMain( $der, true );
			$api->execute();
		} catch ( ApiUsageException $e ) {
			return Status::newFatal(
				$context->msg( 'collaborationkit-hub-edit-apierror',
				$e->getMessageObject() )
			);
		}
		return Status::newGood();
	}
}
