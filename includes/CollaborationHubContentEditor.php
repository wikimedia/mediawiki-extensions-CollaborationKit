<?php
/**
 * @todo Unicode unsafe browsers?
 */
class CollaborationHubContentEditor extends EditPage {

	/** @var string */
	protected $colour;

	function __construct( $page ) {
		parent::__construct( $page );
		// Make human readable the default format for editing, but still
		// save as json. Can be overriden by url ?format=application/json param.
		$this->contentFormat = CollaborationHubContentHandler::FORMAT_WIKI;

		// Nice JavaScript buttons
		$out = $this->getContext()->getOutput();
		$out->addModules( 'ext.CollaborationKit.colour' );
		$out->addModules( 'ext.CollaborationKit.hubimage' );
		$out->addModuleStyles( 'zzext.CollaborationKit.edit.styles' );
		$out->addModuleStyles( 'ext.CollaborationKit.colourbrowser.styles' );
		$out->addJsConfigVars( 'wgCollaborationKitColourList', CollaborationHubContent::getThemeColours() );
	}

	/**
	 * @param $parts array
	 * @return string html
	 */
	protected function getFormFields( $parts ) {
		$displayName = CollaborationListContentEditor::editorInput(
			'input',
			'mw-ck-displayinput',
			'collaborationkit-hubedit-displayname',
			'wpCollabHubDisplayName',
			$parts[0],
			[ 'id' => 'wpCollabHubDisplayName' ]
		);

		$introduction = CollaborationListContentEditor::editorInput(
			'textarea',
			'mw-ck-introductioninput',
			'collaborationkit-hubedit-introduction',
			'wpCollabHubIntroduction',
			$parts[1],
			[ 'rows' => 8, 'id' => 'wpCollabHubIntroduction' ]
		);

		$footer = CollaborationListContentEditor::editorInput(
			'textarea',
			'mw-ck-footerinput',
			'collaborationkit-hubedit-footer',
			'wpCollabHubFooter',
			$parts[2],
			[ 'rows' => 6, 'id' => 'wpCollabHubFooter' ]
		);

		$image = CollaborationListContentEditor::editorInput(
			'input',
			'mw-ck-hubimageinput',
			'collaborationkit-hubedit-image',
			'wpCollabHubImage',
			$parts[3],
			[ 'id' => 'wpCollabHubImage' ]
		);

		$colours = [];
		foreach ( CollaborationHubContent::getThemeColours() as $colour ) {
			$colours[ 'collaborationkit-' . $colour ] = $colour;
		}
		if ( $parts[4] == '' ) {
			$selectedColour = 'blue5';
		} else {
			$selectedColour = $parts[4];
		}
		$colour = CollaborationListContentEditor::editorInput(
			'select',
			'mw-ck-colourinput',
			'collaborationkit-hubedit-colour',
			'wpCollabHubColour',
			$selectedColour,
			[ 'id' => 'wpCollabHubColour' ],
			$colours
		);

		$this->colour = $selectedColour;

		if ( $parts[5] == '' ) {
			$includedContent = '';
		} else {
			$includedContent = $parts[5];
		}
		$content = CollaborationListContentEditor::editorInput(
			'textarea',
			'mw-ck-introductioninput',
			'collaborationkit-hubedit-content',
			'wpCollabHubContent',
			$includedContent,
			[ 'rows' => 18, 'id' => 'wpCollabHubContent' ]
		);

		return $displayName . $image . $colour . $introduction . $content . $footer;
	}

	/**
	 * Renders and adds the editing form to the parser output.
	 */
	protected function showContentForm() {
		if ( $this->contentFormat !== CollaborationHubContentHandler::FORMAT_WIKI ) {
			return parent::showContentForm();
		}

		$parts = explode( CollaborationHubContent::HUMAN_DESC_SPLIT, $this->textbox1, 6 );
		if ( count( $parts ) !== 6 ) {
			return parent::showContentForm();
		}

		$out = RequestContext::getMain()->getOutput();

		$partFields = $this->getFormFields( $parts );
		$out->addHtml( Html::rawElement( 'div', [ 'class' => 'mw-collabkit-modifiededitform' ], $partFields ) );
		$out->prependHtml( Html::openElement( 'div', [ 'class' => 'mw-ck-theme-' . $this->colour ] ) );
		$out->addHtml( Html::closeElement( 'div' ) );
	}

	/**
	 * Converts input from the editing form into the text/x-collabkit
	 * serialization used for processing the edit.
	 * @param &$request WebRequest
	 * @return string|null
	 */
	protected function importContentFormData( &$request ) {
		$format = $request->getVal( 'format', CollaborationListContentHandler::FORMAT_WIKI );
		if ( $format !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::importContentFormData( $request );
		}
		$displayname = trim( $request->getText( 'wpCollabHubDisplayName' ) );
		if ( $displayname === null ) {
			// Only 1 textbox?
			return parent::importContentFormData( $request );
		}

		$introduction = trim( $request->getText( 'wpCollabHubIntroduction', '' ) );
		$footer = trim( $request->getText( 'wpCollabHubFooter', '' ) );
		$image = trim( $request->getText( 'wpCollabHubImage', '' ) );
		$colour = trim( $request->getText( 'wpCollabHubColour', '' ) );
		$content = trim( $request->getText( 'wpCollabHubContent', '' ) );

		return $displayname
			. CollaborationHubContent::HUMAN_DESC_SPLIT
			. $introduction
			. CollaborationHubContent::HUMAN_DESC_SPLIT
			. $footer
			. CollaborationHubContent::HUMAN_DESC_SPLIT
			. $image
			. CollaborationHubContent::HUMAN_DESC_SPLIT
			. $colour
			. CollaborationHubContent::HUMAN_DESC_SPLIT
			. $content;
	}
}
