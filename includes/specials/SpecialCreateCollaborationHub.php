<?php

/**
 * Form to create Collaboration Hubs + migrations/imports/clones
 * Based on code from MassMessage/SpecialMovePage
 *
 * @file
 */

class SpecialCreateCollaborationHub extends SpecialPage {
	/** @var int */
	protected $titleNs;

	/** @var string */
	protected $titleText;

	/** @var string */
	protected $displayName;

	/** @var string */
	protected $icon;

	/** @var string */
	protected $colour;

	/** @var string */
	protected $introduction;

	/** @var bool */
	protected $overwrite;

	/**
	 * @param string $name
	 * @param string $right
	 */
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
		$user = $this->getUser();

		// More thorough permissions checks in doSubmit, but for now...
		if ( !$user->isAllowed( 'createpage' ) ) {
			throw new PermissionsError( 'createpage' );
		}
		if ( !$user->isAllowed( 'editcontentmodel' ) ) {
			throw new PermissionsError( 'editcontentmodel' );
		}

		$this->useTransactionalTimeLimit();

		$this->checkReadOnly();
		$this->setHeaders();
		$this->outputHeader();

		$request = $this->getRequest();
		$out = $this->getContext()->getOutput();

		if ( $request->getVal( 'confirm' ) ) {
			$out->wrapWikiMsg(
				"<div class=\"warningbox\">\n$1\n</div>",
				'collaborationkit-createhub-confirmheader'
			);
		}

		$this->titleText = $request->getText( 'titletext' );
		$this->titleNs = $request->getInt( 'titlens', 0 );
		$this->displayName = $request->getText( 'displayname' );
		$this->icon = $request->getText( 'icon' );
		$this->colour = $request->getText( 'colour' );
		$this->introduction = $request->getText( 'introduction' );
		$this->overwrite = $request->getBool( 'overwrite' );

		if ( $request->getVal( 'action' ) == 'submit' && $request->wasPosted() ) {
			$this->doSubmit();
		} else {
			$this->showForm();
		}
	}

	/**
	 * Show the form
	 */
	private function showForm() {
		$out = $this->getOutput();

		$out->addModules( [
			'ext.CollaborationKit.hubtheme'
		] );
		$out->addModuleStyles( [
			'mediawiki.special',
			'ext.CollaborationKit.edit.styles',
			'ext.CollaborationKit.createhub.styles'
		] );
		$out->addJsConfigVars(
			'wgCollaborationKitColourList',
			CollaborationHubContent::getThemeColours()
		);
		// For some reason we're not using the standard special page intro
		// (formspecialpage didn't use it), so we gotta manually add our own...
		$out->addWikiMsg( 'createcollaborationhub-text' );
		$out->enableOOUI();

		// Not actually correct for FormSpecialPage anymore, but still nice structure...
		$fields = $this->getFormFields();

		$fieldset1 = new OOUI\FieldsetLayout( [
			'classes' => [
				'mw-ck-hub-topform',
				'ck-createcollaborationhub-setup'
			],
			'items' => $fields[0],
		] );
		$fieldset2 = new OOUI\FieldsetLayout( [
			'classes' => [ 'ck-createcollaborationhub-block' ],
			'items' => $fields[1],
		] );

		$form = new OOUI\FormLayout( [
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL( 'action=submit' ),
			'classes' => [ 'ck-createcollaborationhub' ],
		] );
		$form->appendContent( $fieldset1, $fieldset2 );

		$out->addHTML(
			new OOUI\PanelLayout( [
				'expanded' => false,
				'padded' => false,
				'framed' => false,
				'content' => $form,
			] )
		);
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		$allowedNamespaces = $this->getConfig()
			->get( 'CollaborationListAllowedNamespaces' );
		$excludeNamespaces = [];
		foreach ( array_keys( $this->getLanguage()->getNamespaces() ) as $option ) {
			if ( !isset( $allowedNamespaces[$option] ) || !$allowedNamespaces[$option] ) {
				$excludeNamespaces[] = $option;
			}
		}

		$titleInput = [
			'namespace' => [
				'classes' => [ 'mw-ck-namespace-input' ],
				'name' => 'titlens',
				'exclude' => $excludeNamespaces,
				'required' => true
			],
			'title' => [
				'classes' => [ 'mw-ck-title-input' ],
				'placeholder' => $this->msg( 'collaborationkit-createhub-title-placeholder' )->text(),
				'name' => 'titletext',
				'suggestions' => false,
				'required' => true,
				'value' => $this->titleText
			],
			'infusable' => true
		];

		// Our preference is the Project namespace
		if ( $this->titleNs !== null && array_key_exists( $this->titleNs, $allowedNamespaces ) ) {
			$titleInput['namespace']['value'] = $this->titleNs;
		} elseif ( array_key_exists( NS_PROJECT, $allowedNamespaces ) ) {
			$titleInput['namespace']['value'] = NS_PROJECT;
		}

		$fields = [ [], [] ];

		$fields[0][] = new OOUI\FieldLayout(
			new MediaWiki\Widget\ComplexTitleInputWidget( $titleInput ),
			[
				'label' => $this->msg( 'collaborationkit-createhub-title' )->text(),
				'align' => 'top',
				'classes' => [ 'mw-ck-title-input' ],
				'helpInline' => true,
				'help' => $this->msg( 'collaborationkit-createhub-title-help' )->text(),
			]
		);

		$fields[0][] = new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				// Display name can be different from page title
				'name' => 'displayname',
				'value' => $this->displayName
			] ),
			[
				'classes' => [ 'mw-ck-display-input' ],
				'label' => $this->msg( 'collaborationkit-hubedit-displayname' )->text(),
				'helpInline' => true,
				'help' => new OOUI\HtmlSnippet(
					$this->msg( 'collaborationkit-hubedit-displayname-help' )->parse()
				),
				'align' => 'top',
			]
		);
		$fields[0][] = new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				// Display name can be different from page title
				'name' => 'icon',
				'value' => $this->icon
			] ),
			[
				'classes' => [ 'mw-ck-hub-image-input' ],
				'label' => $this->msg( 'collaborationkit-hubedit-image' )->text(),
				'helpInline' => true,
				'help' => new OOUI\HtmlSnippet( $this->msg( 'collaborationkit-hubedit-image-help' )->parse() ),
				'align' => 'top',
			]
		);

		// Colours for the hub styles
		$colours = [];
		foreach ( CollaborationHubContent::getThemeColours() as $colour ) {
			$colours['collaborationkit-' . $colour] = $colour;
		}

		$fields[0][] = new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				// Display name can be different from page title
				'name' => 'colour',
				'options' => $this->getOptions( $colours ),
				'value' => $this->colour
			] ),
			[
				'label' => $this->msg( 'collaborationkit-hubedit-colour' )->text(),
				'align' => 'top',
				'classes' => [ 'mw-ck-colour-input' ],
			]
		);

		$fields[1][] = new OOUI\FieldLayout(
			new OOUI\MultilineTextInputWidget( [
				'name' => 'introduction',
				'rows' => 5,
				'placeholder' => $this->msg( 'collaborationkit-hubedit-introduction-placeholder' )->text(),
				'value' => $this->introduction
			] ),
			[
				'label' => $this->msg( 'collaborationkit-hubedit-introduction' )->text(),
				'align' => 'top',
				'classes' => [ 'mw-ck-introduction-input' ]
			]
		);

		// This form can be used to overwrite existing pages, but the user must
		// confirm first.
		if ( $this->getRequest()->getVal( 'confirm' ) ) {
			$fields[1][] = new OOUI\FieldLayout(
				new OOUI\CheckboxInputWidget( [
					'name' => 'overwrite',
					'value' => '1'
				] ),
				[
					'label' => $this->msg( 'collaborationkit-createhub-confirm' )->text(),
					'classes' => [ 'mw-ck-confirm-input' ],
					'align' => 'inline',
				]
			);
		}

		$fields[1][] = new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'value' => 'submit',
				'label' => $this->msg( 'collaborationkit-createhub-submit' )->text(),
				'flags' => [ 'primary', 'progressive' ],
				'type' => 'submit',
			] ),
			[
				'align' => 'top',
			]
		);

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
			$options[] = [
				'data' => $option,
				'label' => $this->msg( $msgKey )->text()
			];
		}
		return $options;
	}

	/**
	 * @return Status
	 */
	public function doSubmit() {
		$title = Title::makeTitleSafe( $this->titleNs, $this->titleText );
		$user = $this->getUser();
		$permissionManager = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();

		if ( !$title ) {
			return Status::newFatal( 'collaborationkit-createhub-invalidtitle' );
		}

		// TODO: Consider changing to getUserPermissionsErrors for
		// better error message. Possibly as a first step in constructor
		// as the non-title specific error.
		if (
			!$permissionManager->userCan( 'editcontentmodel', $user, $title ) ||
			!$permissionManager->userCan( 'edit', $user, $title )
		) {
			return Status::newFatal( 'collaborationkit-createhub-nopermission' );
		}

		$context = $this->getContext();
		// If a page already exists at the title, ask the user before over-
		// writing the page.
		if ( $title->exists() && !$this->overwrite ) {
			$this->getOutput()->redirect(
				SpecialPage::getTitleFor( 'CreateCollaborationHub' )->getFullURL( [
					'titletext' => $this->titleText,
					'titlens' => $this->titleNs,
					'displayname' => $this->displayName,
					'icon' => $this->icon,
					'colour' => $this->colour,
					'introduction' => $this->introduction,
					'confirm' => 1
				] )
			);

			return Status::newGood();
		}

		// Create member list
		$memberListTitle = Title::newFromText(
			$title->getFullText()
			. '/'
			. $this->msg( 'collaborationkit-hub-pagetitle-members' )
				->inContentLanguage()
				->plain()
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
		$announcementsTitle = Title::newFromText( $title->getFullText()
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
				$e->getMessageObject() )
			);
		}

		// Now, to create the hub itself
		$result = CollaborationHubContentHandler::edit(
			$title,
			$this->displayName,
			$this->icon,
			$this->colour,
			$this->introduction,
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
}
