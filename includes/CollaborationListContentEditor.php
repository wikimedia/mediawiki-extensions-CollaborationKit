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
	}

	protected function showContentForm() {
		if ( $this->contentFormat !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::showContentForm();
		}

		$parts = explode( CollaborationListContent::HUMAN_DESC_SPLIT, $this->textbox1, 3 );
		if ( count( $parts ) !== 3 ) {
			return parent::showContentForm();
		}

		$pageLang = $this->getTitle()->getPageLanguage();
		$attribs = [
			'id' => 'wpCollabDescTextbox',
			'lang' => $pageLang->getHtmlCode(),
			'dir' => $pageLang->getDir()
		];

		$descTitle = wfMessage( 'collaborationkit-listedit-description' )->text();
		$listTitle = wfMessage( 'collaborationkit-listedit-list' )->text();
		$out = RequestContext::getMain()->getOutput();
		$out->addHtml(
			Html::element( 'h2', [ "id" => 'mw-collabkit-desc' ], $descTitle )
			. Html::textarea( 'wpCollabDescTextbox', $parts[0], $attribs )
			. Html::element( 'h2', [ "id" => 'mw-collabkit-list' ], $listTitle )
		);

		$out->addHtml( Html::Hidden( 'wpCollaborationKitOptions', $parts[1] ) );

		$this->showTextbox1( null, $parts[2] );
	}

	protected function importContentFormData( &$request ) {
		$format = $request->getVal( 'format', CollaborationListContentHandler::FORMAT_WIKI );
		if ( $format !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::importContentFormData( $request );
		}
		$desc = trim( $request->getText( 'wpCollabDescTextbox' ) );
		if ( $desc === null ) {
			// Only 1 textbox?
			return parent::importContentFormData( $request );
		}
		$main = trim( $request->getText( 'wpTextbox1', '' ) );
		$options = $request->getText( 'wpCollaborationKitOptions', '' );
		return $desc
			. CollaborationListContent::HUMAN_DESC_SPLIT
			. $options
			. CollaborationListContent::HUMAN_DESC_SPLIT
			. $main;
	}
}
