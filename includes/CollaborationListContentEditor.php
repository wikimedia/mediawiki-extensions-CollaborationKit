<?php

/**
 * Specialized editing interface for CollaborationListContent pages.
 * Extends the notorious EditPage class.
 *
 * @todo Unicode unsafe browsers?
 * @file
 */

class CollaborationListContentEditor extends EditPage {

	public function __construct( Article $page ) {
		parent::__construct( $page );
		// Make human readable the default format for editing, but still
		// save as json. Can be overriden by url ?format=application/json param.
		if ( $this->getCurrentContent()->isValid() ) {
			$this->contentFormat = CollaborationListContentHandler::FORMAT_WIKI;
		}

		$out = $this->getContext()->getOutput();
		$out->addModules( 'mediawiki.htmlform' );
		$out->addModuleStyles( 'ext.CollaborationKit.edit.styles' );
	}

	/**
	 * Prepares a modified edit form
	 */
	protected function showContentForm() {
		if ( $this->contentFormat !== CollaborationListContentHandler::FORMAT_WIKI ) {
			parent::showContentForm();
			return;
		}

		$parts = explode(
			CollaborationKitSerialization::SERIALIZATION_SPLIT,
			$this->textbox1,
			3
		);
		if ( count( $parts ) !== 3 ) {
			parent::showContentForm();
			return;
		}
		$out = $this->getContext()->getOutput();
		$out->addHTML( Html::hidden( 'wpCollaborationKitOptions', $parts[1] ) );

		if ( $parts[2] == '' ) {
			$includedContent = '';
		} else {
			$includedContent = $parts[2];
		}
		$fields = [
			'description' => [
				'type' => 'textarea',
				'cssclass' => 'mw-ck-introduction-input',
				'label-message' => 'collaborationkit-listedit-description',
				'placeholder-message' => 'collaborationkit-listedit-description-placeholder',
				'name' => 'wpCollabListDescription',
				'default' => $parts[0],
				'rows' => 4,
				'id' => 'wpCollabListDescription'
			],
			'content' => [
				'type' => 'textarea',
				'cssclass' => 'mw-ck-content-input',
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

		$out->addHTML( Html::rawElement(
			'div',
			[ 'class' => 'mw-collabkit-modifiededitform' ],
			$partFields
		) );
	}

	/**
	 * Takes contents of edit form and serializes it.
	 *
	 * @param WebRequest &$request
	 * @return string
	 */
	protected function importContentFormData( &$request ) {
		$format = $request->getVal(
			'format',
			CollaborationListContentHandler::FORMAT_WIKI
		);
		if ( $format !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::importContentFormData( $request );
		}
		$desc = trim( $request->getText( 'wpCollabListDescription', '' ) );
		if ( $desc === '' ) {
			// Only 1 textbox?
			return parent::importContentFormData( $request );
		}
		$main = trim( $request->getText( 'wpCollabListContent', '' ) );
		$options = $request->getText( 'wpCollaborationKitOptions', '' );

		return CollaborationKitSerialization::getSerialization( [
			$desc,
			$options,
			$main
		] );
	}
}
