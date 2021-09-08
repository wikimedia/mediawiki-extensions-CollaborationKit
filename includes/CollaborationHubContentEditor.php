<?php

/**
 * Specialized editing interface for CollaborationHubContent pages.
 *
 * @todo Unicode unsafe browsers?
 * @file
 */

class CollaborationHubContentEditor extends EditPage {

	/** @var string */
	protected $colour;

	public function __construct( Article $page ) {
		parent::__construct( $page );
		// Make human readable the default format for editing, but still
		// save as json. Can be overriden by url ?format=application/json param.
		$this->contentFormat = CollaborationHubContentHandler::FORMAT_WIKI;

		// Nice JavaScript buttons
		$out = $this->getContext()->getOutput();
		$out->addModules( [
			'mediawiki.htmlform',
			'ext.CollaborationKit.hubtheme'
		] );
		$out->addModuleStyles( [
			'ext.CollaborationKit.edit.styles',
		] );
		$out->addJsConfigVars(
			'wgCollaborationKitColourList',
			CollaborationHubContent::getThemeColours()
		);
	}

	/**
	 * Prepares form fields.
	 *
	 * @param array $parts
	 * @return string[] html
	 */
	protected function getFormFields( $parts ) {
		$fields = [ [], [] ];

		$fields[0] = [
			'displayname' => [
				'type' => 'text',
				'cssclass' => 'mw-ck-display-input',
				'label-message' => 'collaborationkit-hubedit-displayname',
				'help-message' => 'collaborationkit-hubedit-displayname-help',
				'name' => 'wpCollabHubDisplayName',
				'id' => 'wpCollabHubDisplayName',
				'default' => $parts[0]
			],
			'icon' => [
				'type' => 'text',
				'cssclass' => 'mw-ck-hub-image-input',
				'label-message' => 'collaborationkit-hubedit-image',
				'help-message' => 'collaborationkit-hubedit-image-help',
				'name' => 'wpCollabHubImage',
				'default' => $parts[3],
				'id' => 'wpCollabHubImage'
			],
		];

		// Colour messages:
		// collaborationkit-red, collaborationkit-lightgrey, collaborationkit-skyblue,
		// collaborationkit-bluegrey, collaborationkit-aquamarine, collaborationkit-violet,
		// collaborationkit-salmon, collaborationkit-yellow, collaborationkit-gold,
		// collaborationkit-brightgreen
		$colours = [];
		foreach ( CollaborationHubContent::getThemeColours() as $colour ) {
			$colours['collaborationkit-' . $colour] = $colour;
		}
		if ( $parts[4] == '' ) {
			$selectedColour = 'lightgrey';
		} else {
			$selectedColour = $parts[4];
		}
		$fields[0]['colour'] = [
			'type' => 'select',
			'cssclass' => 'mw-ck-colour-input',
			'name' => 'wpCollabHubColour',
			'id' => 'wpCollabHubColour',
			'label-message' => 'collaborationkit-hubedit-colour',
			'options' => $this->getOptions( $colours ),
			'default' => $selectedColour
		];

		$this->colour = $selectedColour;

		$fields[1]['introduction'] = [
			'type' => 'textarea',
			'cssclass' => 'mw-ck-introduction-input',
			'label-message' => 'collaborationkit-hubedit-introduction',
			'placeholder-message' => 'collaborationkit-hubedit-introduction-placeholder',
			'name' => 'wpCollabHubIntroduction',
			'default' => $parts[1],
			'rows' => 8,
			'id' => 'wpCollabHubIntroduction'
		];

		if ( $parts[5] == '' ) {
			$includedContent = '';
		} else {
			$includedContent = $parts[5];
		}
		$fields[1]['content'] = [
			'type' => 'textarea',
			'cssclass' => 'mw-ck-content-input',
			'label-message' => 'collaborationkit-hubedit-content',
			'help-message' => 'collaborationkit-hubedit-content-help',
			'name' => 'wpCollabHubContent',
			'default' => $includedContent,
			'rows' => 18,
			'id' => 'wpCollabHubContent'
		];

		$fields[1]['footer'] = [
			'type' => 'textarea',
			'cssclass' => 'mw-ck-footer-input',
			'label-message' => 'collaborationkit-hubedit-footer',
			'help-message' => 'collaborationkit-hubedit-footer-help',
			'name' => 'wpCollabHubFooter',
			'default' => $parts[2],
			'rows' => 6,
			'id' => 'wpCollabHubFooter'
		];

		$dummyForm1 = HTMLForm::factory( 'ooui', $fields[0], $this->getContext() );
		$dummyForm2 = HTMLForm::factory( 'ooui', $fields[1], $this->getContext() );

		return [ $dummyForm1->prepareForm()->getBody(),
			$dummyForm2->prepareForm()->getBody() ];
	}

	/**
	 * Build and return the associative array for the content source field.
	 *
	 * @param array $mapping
	 * @return array
	 */
	protected function getOptions( $mapping ) {
		$options = [];
		foreach ( $mapping as $msgKey => $option ) {
			$options[wfMessage( $msgKey )->escaped()] = $option;
		}
		return $options;
	}

	/**
	 * Renders and adds the editing form to the parser output.
	 */
	protected function showContentForm() {
		if ( $this->contentFormat !== CollaborationHubContentHandler::FORMAT_WIKI ) {
			parent::showContentForm();
			return;
		}

		$parts = explode(
			CollaborationKitSerialization::SERIALIZATION_SPLIT,
			$this->textbox1,
			6
		);
		if ( count( $parts ) !== 6 ) {
			parent::showContentForm();
			return;
		}

		$out = $this->getContext()->getOutput();

		$partFields = $this->getFormFields( $parts );
		// See setCollabkitTheme for how the setProperty works.
		$out->setProperty( 'collabkit-theme', $this->colour );
		$out->addHTML( Html::rawElement(
			'div',
			[ 'class' => 'mw-collabkit-modifiededitform' ],
				Html::rawElement(
					'div',
					[ 'class' => 'ck-createcollaborationhub-setup' ],
					$partFields[0]
				) .
				Html::rawElement(
					'div',
					[ 'class' => 'ck-createcollaborationhub-block' ],
					$partFields[1]
				)
			)
		);
	}

	/**
	 * Hook handler for OutputPageBodyAttributes.
	 *
	 * Used to set the color theme for Hub edit pages.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @param array &$bodyAttribs Attributes for the <body> element
	 */
	public static function setCollabkitTheme( OutputPage $out, $skin,
		&$bodyAttribs
	) {
		$theme = $out->getProperty( 'collabkit-theme' );
		if ( $theme ) {
			$themeClass = 'mw-ck-theme-' . $theme;
			if ( !isset( $bodyAttribs['class'] ) ) {
				$bodyAttribs['class'] = $themeClass;
			} else {
				$bodyAttribs['class'] .= ' ' . $themeClass;
			}
		}
	}

	/**
	 * Converts input from the editing form into the text/x-collabkit
	 * serialization used for processing the edit.
	 *
	 * @param WebRequest &$request
	 * @return string|null
	 */
	protected function importContentFormData( &$request ) {
		$format = $request->getVal( 'format', CollaborationListContentHandler::FORMAT_WIKI );
		if ( $format !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::importContentFormData( $request );
		}
		$displayname = trim( $request->getText( 'wpCollabHubDisplayName', '' ) );
		if ( $displayname === '' ) {
			// Only 1 textbox?
			return parent::importContentFormData( $request );
		}

		$introduction = trim( $request->getText( 'wpCollabHubIntroduction', '' ) );
		$footer = trim( $request->getText( 'wpCollabHubFooter', '' ) );
		$image = trim( $request->getText( 'wpCollabHubImage', '' ) );
		$colour = trim( $request->getText( 'wpCollabHubColour', '' ) );
		$content = trim( $request->getText( 'wpCollabHubContent', '' ) );

		return CollaborationKitSerialization::getSerialization( [
			$displayname,
			$introduction,
			$footer,
			$image,
			$colour,
			$content
		] );
	}
}
