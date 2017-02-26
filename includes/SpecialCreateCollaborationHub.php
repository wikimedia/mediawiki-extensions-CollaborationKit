<?php

/**
 * Form to create Collaboration Hubs + migrations/imports/clones
 * Based on code from MassMessage
 *
 * @file
 */

class SpecialCreateCollaborationHub extends FormSpecialPage {

	public function __construct( $name = 'CreateCollaborationHub', $right = 'createpage' ) {
		// Note: The right check is primarily for UI. There are
		// additional checks later on.
		parent::__construct( $name, $right );
	}

	/**
	 * @param $par string
	 */
	public function execute( $par ) {
		$out = $this->getContext()->getOutput();
		$out->addModules( [
			'ext.CollaborationKit.hubtheme'
		] );
		$out->addModuleStyles( 'ext.CollaborationKit.edit.styles' );
		$out->addJsConfigVars( 'wgCollaborationKitColourList', CollaborationHubContent::getThemeColours() );

		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		$allowedNamespaces = $this->getConfig()->get( 'CollaborationListAllowedNamespaces' );
		$namespaceNames = $this->getLanguage()->getNamespaces();
		$namespaceChoices = [];
		foreach ( $allowedNamespaces as $nsIndex => $nsCanBeUsed ) {
			$namespaceChoices[$namespaceNames[$nsIndex]] = $nsIndex;
		}

		$fields = [
			// autofilled from how they got here, hopefully

			'namespace' => [
				'type' => 'select',
				'options' => $namespaceChoices,
				'cssclass' => 'mw-ck-namespace-input',
				'label-message' => 'collaborationkit-createhub-title',
				'required' => true
			],
			'title' => [
				'type' => 'text',
				'cssclass' => 'mw-ck-title-input',
				'placeholder-message' => 'collaborationkit-createhub-title-placeholder',
				'help-message' => 'collaborationkit-createhub-title-help',
				'required' => true
			],
			// Display name can be different from page title
			'display_name' => [
				'type' => 'text',
				'cssclass' => 'mw-ck-display-input',
				'label-message' => 'collaborationkit-hubedit-displayname',
				'help-message' => 'collaborationkit-hubedit-displayname-help'
			],
			// Hub image/icon thing
			'icon' => [
				'type' => 'text',
				'cssclass' => 'mw-ck-hub-image-input',
				'label-message' => 'collaborationkit-hubedit-image',
				'help-message' => 'collaborationkit-hubedit-image-help'
			],
		];

		// Our preference is the Project namespace
		if ( in_array( 4, $allowedNamespaces ) ) {
			$fields['namespace']['default'] = 4;
		}

		// Colours for the hub styles
		$colours = [];
		foreach ( CollaborationHubContent::getThemeColours() as $colour ) {
			$colours['collaborationkit-' . $colour] = $colour;
		}
		$fields['colour'] = [
			'type' => 'select',
			'cssclass' => 'mw-ck-colour-input',
			'label-message' => 'collaborationkit-hubedit-colour',
			'options' => $this->getOptions( $colours ),
			'default' => 'lightgrey'
		];

		/* Comment this out until it's actually implemented (T135408)
		// Content source options
		$fields['content_source'] = [
			'type' => 'select',
			'options' => $this->getOptions( [
				'collaborationkit-createhub-new' => 'new',
				'collaborationkit-createhub-import' => 'import',
				'collaborationkit-createhub-clone' => 'clone',
			] ),
			'default' => 'new', // might want to change default to clone from the default? (TODO add a canned default as example and stuff: T136470)
			'label-message' => 'collaborationkit-createhub-content',
			'cssclass' => 'mw-ck-source-options-input'
		];
		$fields['source'] = [
			'type' => 'text',
			'label-message' => 'collaborationkit-createhub-source',
			'hide-if' => [ '===', 'wpcontent_source', 'new' ],
			'cssclass' => 'mw-ck-source-input'
		];
		*/

		$fields['introduction'] = [
			'type' => 'textarea',
			'rows' => 5,
			'label-message' => 'collaborationkit-hubedit-introduction',
			'cssclass' => 'mw-ck-introduction-input',
			'placeholder-message' => 'collaborationkit-hubedit-introduction-placeholder'
		];

		return $fields;
	}

	/**
	 * Build and return the associative array for the content source field.
	 * @param $mapping array
	 * @return array
	 */
	protected function getOptions( $mapping ) {
		$options = [];
		foreach ( $mapping as $msgKey => $option ) {
			$options[$this->msg( $msgKey )->escaped()] = $option;
		}
		return $options;
	}

	/**
	 * @param $data array
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$namespaces = $this->getLanguage()->getNamespaces();
		$pagename = $namespaces[$data['namespace']] . ':' . $data['title'];
		$title = Title::newFromText( $pagename );
		if ( !$title ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}

		$user = $this->getUser();
		// TODO: Consider changing to getUserPermissionsErrors for
		// better error message. Possibly as a first step in constructor
		// as the non-title specific error.
		if (
			!$title->userCan( 'edit', $user ) ||
			!$title->userCan( 'create', $user ) ||
			!$title->userCan( 'editcontentmodel', $user )
		) {
			return Status::newFatal( 'collaborationkit-createhub-nopermission' );
		}

		/* Comment this out until it's actually implemented (T135408)
		// ACTUAL STUFF HERE
		if ( $data['content_source'] !== 'new' ) { // Importing from wikitext
			$source = Title::newFromText( $data['source'] );
			if ( !$source ) {
				return Status::newFatal( 'collaborationkit-createhub-invalidsource' );
			}

			if ( $data['content_source'] === 'clone' ) {
				// Copy another hub
				// Just copy some of the bits...

				// TODO prefill the actual content
			} elseif ( $data['content_source'] === 'import' ) {
				// Do some magic based on the source:
				// If wikiproject x project: get module list, recreate modules
				// If regular page: pull headers

				// TODO prefill the actual content
			}
			// Render preview?
		} else {

			// ...?
		}
		*/

		$title = Title::newFromText( $pagename );
		if ( !$title ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}

		$memberListTitle = Title::newFromText( $pagename . '/' . $this->msg( 'collaborationkit-hub-pagetitle-members' ) );
		if ( !$memberListTitle ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}
		$memberResult = CollaborationListContentHandler::postMemberList(
			$memberListTitle,
			$this->msg( 'collaborationkit-createhub-editsummary' )->inContentLanguage()->plain(),
			$this->getContext()
		);

		if ( !$memberResult->isGood() ) {
			return $memberResult;
		}

		$announcementsTitle = Title::newFromText( $pagename . '/' . $this->msg( 'collaborationkit-hub-pagetitle-announcements' )->inContentLanguage()->plain() );
		if ( !$announcementsTitle ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}
		// Ensure that a valid context is provided to the API in unit tests
		$context = $this->getContext();
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $announcementsTitle->getFullText(),
				'text' => "* " . $context->msg( 'collaborationkit-hub-announcements-initial' )->inContentLanguage()->plain() . " ~~~~~",
				'summary' => $context->msg( 'collaborationkit-createhub-editsummary' )->inContentLanguage()->plain(),
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

		$result = CollaborationHubContentHandler::edit(
			$title,
			$data['display_name'],
			$data['icon'],
			$data['colour'],
			$data['introduction'],
			'',
			[],
			$this->msg( 'collaborationkit-createhub-editsummary' )->inContentLanguage()->plain(),
			$this->getContext()
		);

		if ( !$result->isGood() ) {
			return $result;
		}

		// Once all the pages we want to create are created, we send them to the first one
		$this->getOutput()->redirect( $title->getFullURL() );
		return Status::newGood();
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * Set the form format to ooui for consistency with the rest of the ck stuff
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}
