<?php

/**
 * Form to create features for Collaboration Hubs
 * Based on code from MassMessage
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

class SpecialCreateHubFeature extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'CreateHubFeature' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		if ( !$this->getUser()->isAllowed( 'createpage' ) ) {
			throw new PermissionsError( 'createpage' );
		}

		$output = $this->getContext()->getOutput();
		$output->addModules( 'ext.CollaborationKit.iconbrowser' );
		$output->addModuleStyles( [
			'ext.CollaborationKit.createhubfeature.styles',
			'ext.CollaborationKit.edit.styles'
		] );
		$output->addJsConfigVars(
			'wgCollaborationKitIconList',
			CollaborationKitImage::getCannedIcons()
		);
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		// Allow collaboration hub to be passed via parameter (full page title) ?collaborationhub=
		// Allow feature name to be passed via parameter (subpage title) ?feature=
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

		$icons = CollaborationKitImage::getCannedIcons();
		$iconChoices = array_combine( $icons, $icons );

		$fields = [
			'collaborationhub' => [
				'type' => 'title',
				'cssclass' => 'mw-ck-fulltitle-input',
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
				'options' => $iconChoices,
				'default' => 'circlestar'
			],
			'contenttype' => [
				'type' => 'radio',
				'cssclass' => 'mw-ck-content-type-input',
				'label-message' => 'collaborationkit-createhubfeature-contenttype',
				'options' => [
					$this
						->msg( 'collaborationkit-createhubfeature-freetext' )
						->escaped() => 'wikitext',
					$this
						->msg( 'collaborationkit-createhubfeature-articlelist' )
						->escaped() => 'CollaborationListContent'
				]
			]
		];

		// If either of these fields is set, that means the user came to the
		// special page by way of a special workflow, meaning that the name of
		// the hub and/or the feature is already known. Changing it would cause
		// problems (e.g. the hub saying the feature is called one thing and
		// then the user changes their mind) so we disable further edits in the
		// middle of the workflow.
		if ( $defaultCollabHub != '' ) {
			$fields['collaborationhub']['readonly'] = true;
		}
		if ( $defaultFeatureName != '' ) {
			$fields['featurename']['readonly'] = true;
		}

		return $fields;
	}

	/**
	 * The result of submitting the form
	 *
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$collaborationHub = $data['collaborationhub'];
		$hubTitleObject = Title::newFromText( $collaborationHub );
		$permissionManager = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();
		$user = $this->getUser();

		// This special page can only be used to create subpages of Collaboration
		// Hubs. This checks if the parent page is one.

		if ( !$hubTitleObject->exists() ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-hubdoesnotexist' );
		}

		if ( $hubTitleObject->getContentModel() != 'CollaborationHubContent' ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-hubisnotahub' );
		}

		$featureName = $data['featurename'];
		$titleText = $collaborationHub . '/' . $featureName;
		$title = Title::newFromText( $titleText );
		$contentModel = $data['contenttype'];

		if ( !$title ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-invalidtitle' );
		} elseif ( $title->exists() ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-exists' );
		} elseif (
			!$permissionManager->userCan( 'edit', $user, $title )
		) {
			return Status::newFatal( 'collaborationkit-createhubfeature-nopermission' );
		} elseif ( $contentModel == 'CollaborationListContent' &&
			!$permissionManager->userCan( 'editcontentmodel', $user, $title )
		) {
			return Status::newFatal( 'collaborationkit-createhubfeature-nopermission-contentmodel' );
		}

		// Create feature
		if ( $contentModel != 'wikitext'
			&& $contentModel != 'CollaborationListContent' ) {
			return Status::newFatal( 'collaborationkit-createhubfeature-invalidcontenttype' );
		}

		if ( $contentModel == 'wikitext' ) {
			$contentFormat = 'text/x-wiki';
		} elseif ( $contentModel == 'CollaborationListContent' ) {
			$contentFormat = 'application/json';
		} else {
			return Status::newFatal( 'collaborationkit-createhubfeature-invalidcontenttype' );
		}

		// Create empty page by default; exception is if there needs to be
		// something such as JSON.
		$initialContent = '';
		if ( $contentModel == 'CollaborationListContent' ) {
			$contentModelObj = ContentHandler::getForModelID( $contentModel );
			 // ^ Roan recommends renaming $contentModel to $contentModelID
			$initialContent = $contentModelObj->serializeContent(
				$contentModelObj->makeEmptyContent()
			);
		}

		$summary = $this
			->msg( 'collaborationkit-createhubfeature-editsummary' )
			->plain();

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
		} catch ( ApiUsageException $e ) {
			return Status::newFatal(
				$context->msg(
					'collaborationkit-hub-edit-apierror',
					$e->getMessageObject()
				)
			);
		}

		// Update hub with link to new feature
		$newFeature = [ 'title' => $titleText, 'display_title' => $featureName ];

		if ( $data[ 'icon' ] ) {
			$newFeature['image'] = $data[ 'icon' ];
		}

		$hubWikiPageObject = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $hubTitleObject );
		$hubRawContent = $hubWikiPageObject->getContent();
		$hubContent = json_decode( $hubRawContent->serialize(), true );

		// Don't actually update the hub if the hub includes the feature.
		$found = false;
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		foreach ( $hubContent['content'] as $c ) {
			if ( $c['title'] === $titleText ) {
				$found = true;
				break;
			}
		}

		if ( !$found ) {
			$hubContent['content'][] = $newFeature;
			$newHubRawContent = json_encode( $hubContent );
			$editHubSummary = $this
				->msg( 'collaborationkit-createhubfeature-hubeditsummary', $featureName )
				->plain();

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
			} catch ( ApiUsageException $e ) {
				return Status::newFatal(
					$context->msg(
						'collaborationkit-hub-edit-apierror',
						$e->getMessageObject()
					)
				);
			}
		}

		// Purge the hub's cache so that it doesn't say "feature does not exist"
		$hubTitleObject->invalidateCache();

		$this->getOutput()->redirect( $title->getFullURL() );

		return Status::newGood();
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}
