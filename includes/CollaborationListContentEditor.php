<?php
/**
 * @todo Unicode unsafe browsers?
 */
class CollaborationListContentEditor extends EditPage {

	function __construct( $page ) {
		parent::__construct( $page );
		// Make human readable the default format for editing, but still
		// save as json. Can be overriden by url ?format=application/json param.
		$this->contentFormat = CollaborationListContentHandler::FORMAT_WIKI;
	}

	protected function showContentForm() {
		if ( $this->contentFormat !== CollaborationListContentHandler::FORMAT_WIKI ) {
			return parent::showContentForm();
		}
		$parts = explode( CollaborationListContent::HUMAN_DESC_SPLIT, $this->textbox1, 2 );
		if ( count( $parts ) !== 2 ) {
			return;
		}

		$pageLang = $this->getTitle()->getPageLanguage();
		$attribs = [
			'id' => 'wpCollabDescTextbox',
			'lang' => $pageLang->getHtmlCode(),
			'dir' => $pageLang->getDir()
		];

		$descTitle = wfMessage( 'collaborationkit-listedit-description' )->text();
		$listTitle = wfMessage( 'collaborationkit-listedit-list' )->text();
		RequestContext::getMain()->getOutput()->addHtml(
			Html::element( 'h2', [ "id" => 'mw-collabkit-desc' ], $descTitle )
			. Html::textarea( 'wpCollabDescTextbox', $parts[0], $attribs )
			. Html::element( 'h2', [ "id" => 'mw-collabkit-list' ], $listTitle )
		);

		$this->showTextbox1( null, $parts[1] );
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
		return $desc . CollaborationListContent::HUMAN_DESC_SPLIT . $main;
	}
}
