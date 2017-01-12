<?php
/**
 * @todo Unicode unsafe browsers?
 */
class CollaborationListContentEditor extends EditPage {

	function __construct( $page ) {
		parent::__construct( $page );
		// Make human readable the default format for editing, but still
		// save as json. Can be overriden by url ?format=application/json param.
		if ( $this->getCurrentContent()->isValid() ) {
			$this->contentFormat = CollaborationListContentHandler::FORMAT_WIKI;
		}

		$out = $this->getContext()->getOutput();
		$out->addModules( 'mediawiki.htmlform' );
		$out->addModuleStyles( 'zzext.CollaborationKit.edit.styles' );
	}

	protected function showContentForm() {
		if ( $this->contentFormat !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::showContentForm();
		}

		$parts = explode( CollaborationListContent::HUMAN_DESC_SPLIT, $this->textbox1, 3 );
		if ( count( $parts ) !== 3 ) {
			return parent::showContentForm();
		}
		$out = RequestContext::getMain()->getOutput();
		$out->addHtml( Html::Hidden( 'wpCollaborationKitOptions', $parts[1] ) );

		if ( $parts[2] == '' ) {
			$includedContent = '';
		} else {
			$includedContent = $parts[2];
		}
		$fields = [
			'description' => [
				'type' => 'textarea',
				'cssclass' => 'mw-ck-introductioninput',
				'label-message' => 'collaborationkit-listedit-description',
				'placeholder' => 'collaborationkit-listedit-description-placeholder',
				'name' => 'wpCollabListDescription',
				'default' => $parts[0],
				'rows' => 4,
				'id' => 'wpCollabListDescription'
			],
			'content' => [
				'type' => 'textarea',
				'cssclass' => 'mw-ck-textboxmain',
				'label-message' => 'collaborationkit-listedit-list',
				'help-message' => 'collaborationkit-listedit-list-help',
				'name' => 'wpCollabListContent',
				'default' => $includedContent,
				'rows' => 18,
				'id' => 'wpCollabListContent'
			]
		];

		$dummyForm = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$partFields = $dummyForm->prepareForm()->getBody();

		$out->addHtml( Html::rawElement( 'div', [ 'class' => 'mw-collabkit-modifiededitform' ], $partFields ) );
	}

	protected function importContentFormData( &$request ) {
		$format = $request->getVal( 'format', CollaborationListContentHandler::FORMAT_WIKI );
		if ( $format !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::importContentFormData( $request );
		}
		$desc = trim( $request->getText( 'wpCollabListDescription' ) );
		if ( $desc === null ) {
			// Only 1 textbox?
			return parent::importContentFormData( $request );
		}
		$main = trim( $request->getText( 'wpCollabListContent', '' ) );
		$options = $request->getText( 'wpCollaborationKitOptions', '' );
		return $desc
			. CollaborationListContent::HUMAN_DESC_SPLIT
			. $options
			. CollaborationListContent::HUMAN_DESC_SPLIT
			. $main;
	}
}
