<?php
/**
 * @todo Unicode unsafe browsers?
 */
class CollaborationHubContentEditor extends EditPage {

	function __construct( $page ) {
		parent::__construct( $page );
		// Make human readable the default format for editing, but still
		// save as json. Can be overriden by url ?format=application/json param.
		$this->contentFormat = CollaborationHubContentHandler::FORMAT_WIKI;
	}

	/**
	 * Build and return the aossociative array for the content source field.
	 * @param $mapping array
	 * @return array
	 */
	protected function getOptions( $mapping ) {
		$options = [];
		foreach ( $mapping as $msgKey => $option ) {
			$options[] = [ 'label' => wfMessage( $msgKey )->escaped(), 'data' => $option ];
		}
		return $options;
	}

	/**
	 * @param $parts array
	 * @return array
	 */
	protected function getFormFields( $parts ) {

		$fields = [
			// Display name can be different from page title
			'display_name' => new OOUI\FieldLayout(
				new OOUI\TextInputWidget( [
					'name' => 'wpCollabHubDisplayName',
					'id' => 'wpCollabHubDisplayName',
					'type' => 'text',
					'class' => 'mw-ck-displayinput',
					'value' => $parts[0]
					] ),
				[
					'label' => wfMessage( 'collaborationkit-hubedit-displayname' )->text(),
					'align' => 'top'
				] ),
			'introduction' => new OOUI\FieldLayout(
				new OOUI\TextInputWidget( [
					'multiline' => true,
					'name' => 'wpCollabHubIntroduction',
					'id' => 'wpCollabHubIntroduction',
					'type' => 'textarea',
					'rows' => 5,
					'class' => 'mw-ck-introductioninput',
					'value' => $parts[1]
					] ),
				[
					'label' => wfMessage( 'collaborationkit-hubedit-introduction' )->text(),
					'align' => 'top'
				] ),
			'footer' => new OOUI\FieldLayout(
				new OOUI\TextInputWidget( [
					'multiline' => true,
					'name' => 'wpCollabHubFooter',
					'id' => 'wpCollabHubFooter',
					'type' => 'textarea',
					'rows' => 5,
					'class' => 'mw-ck-introductioninput',
					'value' => $parts[2]
					] ),
				[
					'label' => wfMessage( 'collaborationkit-hubedit-footer' )->text(),
					'align' => 'top'
				] ),
			// Hub image/icon thing
			'image' => new OOUI\FieldLayout(
				new OOUI\TextInputWidget( [
					'name' => 'wpCollabHubImage',
					'id' => 'wpCollabHubImage',
					'type' => 'text',
					'class' => 'mw-ck-iconinput',
					'value' => $parts[3]
					] ),
				[
					'label' => wfMessage( 'collaborationkit-hubedit-image' )->text(),
					'align' => 'top'
				] ),
		];
		// Colours for the hub styles
		$colours = [];
		foreach ( CollaborationHubContent::getThemeColours() as $colour ) {
			$colours[ 'collaborationkit-' . $colour ] = $colour;
		}

		if ( $parts[4] == '' ) {
			$selectedColour = 'blue5';
		} else {
			$selectedColour = $parts[4];
		}

		$fields['colour'] = new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'name' => 'wpCollabHubColour',
				'id' => 'wpCollabHubColour',
				'type' => 'select',
				'options' => $this->getOptions( $colours ),
				'class' => 'mw-ck-colourinput',
				'value' => $selectedColour
				] ),
			[
				'label' => wfMessage( 'collaborationkit-hubedit-colour' )->text(),
				'align' => 'top'
			] );

		if ( $parts[5] == '' ) {
			$includedContent = '';
		} else {
			$includedContent = $parts[5];
		}

		$fields['content'] = new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'multiline' => true,
				'name' => 'wpCollabHubContent',
				'id' => 'wpCollabHubContent',
				'type' => 'textarea',
				'rows' => 10,
				'class' => 'mw-ck-introductioninput',
				'value' => $includedContent
				] ),
			[
				'label' => wfMessage( 'collaborationkit-hubedit-content' )->text(),
				'align' => 'top'
			] );

		return $fields;
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
		$pageLang = $this->getTitle()->getPageLanguage();

		$formFields = $this->getFormFields( $parts );

		$htmlForm = new OOUI\FieldsetLayout( [ 'items' => $formFields ] );
		$out->enableOOUI();
		$out->addHtml( $htmlForm );
	}

	/**
	 * Required as a callback by the parent class, but not used as
	 * we have validation logic elsewhere that works just fine.
	 * @param $formData
	 */
	static function trySubmit( $formData ) {
		return true;
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

	protected function getDisplayFormat() {
		return 'ooui';
	}
}
