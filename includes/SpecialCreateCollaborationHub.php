<?php

/**
 * Form to create Collaboration Hubs + migrations/imports/clones
 * Based on code from MassMessage
 *
 * @file
 */

class SpecialCreateCollaborationHub extends FormSpecialPage {

	public function __construct( $name = 'CreateCollaborationHub',
		$right = 'createpage'
	) {
		// Note: The right check is primarily for UI. There are
		// additional checks later on.
		parent::__construct( $name, $right );
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

		$out = $this->getContext()->getOutput();
		$out->addModules( [
			'ext.CollaborationKit.hubtheme'
		] );
		$out->addModuleStyles( 'ext.CollaborationKit.edit.styles' );
		$out->addJsConfigVars(
			'wgCollaborationKitColourList',
			CollaborationHubContent::getThemeColours()
		);

		if ( $this->getRequest()->getVal( 'confirm' ) ) {
			$out->wrapWikiMsg(
				"<div class=\"warningbox\">\n$1\n</div>",
				'collaborationkit-createhub-confirmheader'
			);
		}
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		$allowedNamespaces = $this
			->getConfig()
			->get( 'CollaborationListAllowedNamespaces' );
		$namespaceNames = $this->getLanguage()->getNamespaces();
		$namespaceChoices = [];
		foreach ( $allowedNamespaces as $nsIndex => $nsCanBeUsed ) {
			$namespaceChoices[$namespaceNames[$nsIndex]] = $nsIndex;
		}

		$fields = [
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

		$fields['introduction'] = [
			'type' => 'textarea',
			'rows' => 5,
			'label-message' => 'collaborationkit-hubedit-introduction',
			'cssclass' => 'mw-ck-introduction-input',
			'placeholder-message' => 'collaborationkit-hubedit-introduction-placeholder'
		];

		// If these fields are included in GET parameter, have them as default
		// values on the form.
		foreach ( [
			'namespace',
			'icon',
			'display_name',
			'icon',
			'colour',
			'introduction' ]
			as $fieldName ) {
			if ( $this->getRequest()->getVal( $fieldName ) ) {
				$fields[ $fieldName ][ 'default' ] = $this->getRequest()->getVal( $fieldName );
			}
		}

		// "title" is special because it's already taken by MediaWiki.
		if ( $this->getRequest()->getVal( 'hubtitle' ) ) {
			$fields[ 'title' ][ 'default' ] = $this->getRequest()->getVal( 'hubtitle' );
		}

		// This form can be used to overwrite existing pages, but the user must
		// confirm first.
		if ( $this->getRequest()->getVal( 'confirm' ) ) {
			$fields['do_overwrite'] = [
				'type' => 'check',
				'label-message' => 'collaborationkit-createhub-confirm',
				'cssclass' => 'mw-ck-confirm-input'
			];
		}
		return $fields;
	}

	/**
	 * Build and return the associative array for the content source field.
	 * @param array $mapping
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
	 * @param array $data
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

		$context = $this->getContext();

		$do_overwrite = $context->getRequest()->getBool( 'wpdo_overwrite' );

		// If a page already exists at the title, ask the user before over-
		// writing the page.
		if ( $title->exists() && !$do_overwrite ) {
			$this->getOutput()->redirect(
				SpecialPage::getTitleFor( 'CreateCollaborationHub' )->getFullURL( [
					'namespace' => $data['namespace'],
					'hubtitle' => $data['title'],
					'display_name' => $data['display_name'],
					'icon' => $data['icon'],
					'colour' => $data['colour'],
					'introduction' => $data['introduction'],
					'confirm' => 1
				] )
			);
			return;
		}

		// Create member list
		$memberListTitle = Title::newFromText(
			$pagename
			. '/'
			. $this->msg( 'collaborationkit-hub-pagetitle-members' )
		);
		if ( !$memberListTitle ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}
		$memberResult = CollaborationListContentHandler::postMemberList(
			$memberListTitle,
			$this->msg( 'collaborationkit-createhub-editsummary' )
				->inContentLanguage()
				->plain(),
			$context
		);

		if ( !$memberResult->isGood() ) {
			return $memberResult;
		}

		// Create announcements page
		$announcementsTitle = Title::newFromText( $pagename
			. '/'
			. $this->msg( 'collaborationkit-hub-pagetitle-announcements' )
			->inContentLanguage()
			->plain()
		);
		if ( !$announcementsTitle ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}

		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $announcementsTitle->getFullText(),
				'text' => "* " . $context
					->msg( 'collaborationkit-hub-announcements-initial' )
					->inContentLanguage()
					->plain()
					. " ~~~~~",
				'summary' => $context
					->msg( 'collaborationkit-createhub-editsummary' )
					->inContentLanguage()
					->plain(),
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
				$e->getCodeString() )
			);
		}

		// Now, to create the hub itself
		$result = CollaborationHubContentHandler::edit(
			$title,
			$data['display_name'],
			$data['icon'],
			$data['colour'],
			$data['introduction'],
			'',
			[],
			$this
				->msg( 'collaborationkit-createhub-editsummary' )
				->inContentLanguage()
				->plain(),
			$context
		);

		if ( !$result->isGood() ) {
			return $result;
		}

		// Once all the pages we want to create are created, we send them to
		// the first one
		$this->getOutput()->redirect( $title->getFullURL() );
		return Status::newGood();
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * Set the form format to ooui for consistency with the rest of the ck stuff
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}
