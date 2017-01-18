<?php

/**
 * Form to create features for Collaboration Hubs
 * Based on code from MassMessage
 *
 * @file
 */

class SpecialCreateHubFeature extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'CreateHubFeature' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$output = $this->getContext()->getOutput();
		$output->addModules( 'ext.CollaborationKit.iconbrowser' );
		$output->addModuleStyles( 'ext.CollaborationKit.edit.styles' );
		$output->addJsConfigVars( 'wgCollaborationKitIconList', CollaborationKitIcon::getCannedIcons() );
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {

		// Allow the collaboration hub to be passed via parameter (full page title) ?collaborationhub=
		// Allow the feature name to be passed via parameter (subpage title) ?feature=
		if ( $this->getRequest()->getVal( 'collaborationhub' ) ) {
			$defaultCollabHub = $this->getRequest()->getVal( 'collaborationhub' );
		} else {
			$defaultCollabHub = '';
		}

		if ( $this->getRequest()->getVal( 'feature' ) ) {
			$defaultFeatureName = $this->getRequest()->getVal( 'feature' );
		} else {
			$defaultFeatureName = '';
		}

		$icons = CollaborationKitIcon::getCannedIcons();
		$iconChoices = array_combine( $icons, $icons );

		$fields = [
			'collaborationhub' => [
				'type' => 'title',
				'cssclass' => 'mw-ck-title-input',
				'label-message' => 'collaborationkit-createhubfeature-collaborationhub',
				'default' => $defaultCollabHub
			],
			'featurename' => [
				'type' => 'text',
				'cssclass' => 'mw-ck-display-input',
				'label-message' => 'collaborationkit-createhubfeature-featurename',
				'default' => $defaultFeatureName
			],
			// TODO replace with subclassed image selector
			'icon' => [
				'type' => 'combobox',
				'cssclass' => 'mw-ck-icon-input',
				'label-message' => 'collaborationkit-createhubfeature-icon',
				'help-message' => 'collaborationkit-createhubfeature-icon-help',
				'options' => $iconChoices
			],
			'contenttype' => [
				'type' => 'radio',
				'cssclass' => 'mw-ck-content-type-input',
				'label-message' => 'collaborationkit-createhubfeature-contenttype',
				'options' => [ $this->msg( 'collaborationkit-createhubfeature-freetext' )->text() => 'wikitext',
					$this->msg( 'collaborationkit-createhubfeature-articlelist' )->text() => 'CollaborationListContent' ]
			]
		];

		// If either of these fields is set, that means the user came to the special page
		// by way of a special workflow, meaning that the name of the hub and/or the feature
		// is already known. Changing it would cause problems (e.g. the hub saying the feature
		// is called one thing and then the user changes their mind) so we disable further edits
		// in the middle of the workflow.
		if ( $defaultCollabHub != '' ) {
			$fields['collaborationhub']['readonly'] = true;
		}
		if ( $defaultFeatureName != '' ) {
			$fields['featurename']['readonly'] = true;
		}

		return $fields;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$collaborationHub = $data[ 'collaborationhub' ];
		$featureName = $data[ 'featurename' ];

		$titleText = $collaborationHub . '/' . $featureName;

		$title = Title::newFromText( $titleText );
		if ( !$title ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-invalidtitle' );
		} elseif ( $title->exists() ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-exists' );
		} elseif (
			!$title->userCan( 'edit' ) ||
			!$title->userCan( 'create' ) ||
			!$title->userCan( 'editcontentmodel' )
		) {
			return Status::newFatal( 'collaborationkit-createhubfeature-nopermission' );
		}

		// Update hub with link to new feature
		$newFeature = [ 'title' => $titleText, 'display_title' => $featureName ];

		if ( $data[ 'icon' ] ) {
			$newFeature['image'] = $data[ 'icon' ];
		}

		$hubTitleObject = Title::newFromText( $collaborationHub );

		if ( !$hubTitleObject->exists() ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-hubdoesnotexist' );
		}

		if ( $hubTitleObject->getContentModel() != "CollaborationHubContent" ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-hubisnotahub' );
		}

		$hubWikiPageObject = WikiPage::factory( $hubTitleObject );
		$hubRawContent = $hubWikiPageObject->getContent();
		$hubContent = json_decode( $hubRawContent->serialize(), true );

		// Don't actually update the hub if the hub includes the feature.
		$found = false;
		foreach ( $hubContent['content'] as $c ) {
			if ( $c['title'] === $titleText ) {
				$found = true;
				break;
			}
		}

		if ( !$found ) {
			$hubContent['content'][] = $newFeature;
			$newHubRawContent = json_encode( $hubContent );
			$editHubSummary = $this->msg( 'collaborationkit-createhubfeature-hubeditsummary', $featureName )->plain();

			$context = $this->getContext();
			$der = new DerivativeContext( $context );
			$request = new DerivativeRequest(
				$context->getRequest(),
				[
					'action' => 'edit',
					'title' => $hubTitleObject->getFullText(),
					'contentmodel' => 'CollaborationHubContent',
					'text' => $newHubRawContent,
					'summary' => $editHubSummary,
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
		}

		// Create feature
		$contentModel = $data[ 'contenttype' ];
		if ( $contentModel != 'wikitext' && $contentModel != 'CollaborationListContent' ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-invalidcontenttype' );
		}

		if ( $contentModel == 'wikitext' ) {
			$contentFormat = 'text/x-wiki';
		} elseif ( $contentModel == 'CollaborationListContent' ) {
			$contentFormat = 'application/json';
		} else {
			return Status::newFatal( 'collaborationkit-createhubfeature-invalidcontenttype' );
		}

		$initialContent = ''; // Create empty page by default; exception is if there needs to be something such as JSON.
		if ( $contentModel == 'CollaborationListContent' ) {
			// FIXME why are we redefining this here? Can't we reuse something from collaborationlistcontenthandler, which already has default content?
			$initialContent = '{ "displaymode": "normal", "columns": [ { "items":[] } ], "options":{}, "description":"" }';
		}

		$summary = $this->msg( 'collaborationkit-createhubfeature-editsummary' )->plain();

		$context = $this->getContext();
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $title->getFullText(),
				'contentmodel' => $contentModel,
				'contentformat' => $contentFormat,
				'text' => $initialContent,
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

		// Purge the hub's cache so that it doesn't say "feature does not exist"
		$hubTitleObject->invalidateCache();

		// Once all the pages we want to create are created, we send them to the first one
		$this->getOutput()->redirect( $title->getFullUrl() );
		return Status::newGood();
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * Set the form format to ooui for consistency with the rest of the ck stuff
	 * @param HTMLForm $form
	 *
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}
