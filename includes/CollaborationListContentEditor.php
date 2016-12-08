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

		$pageLang = $this->getTitle()->getPageLanguage();
		$attribs = [
			'id' => 'wpCollabDescTextbox',
			'lang' => $pageLang->getHtmlCode(),
			'dir' => $pageLang->getDir()
		];

		$html = Html::openElement( 'div', [ 'class' => 'mw-collabkit-modifiededitform' ] );
		// Extra form fields
		$html .= self::editorInput( 'textarea', 'mw-collabkit-desc', 'collaborationkit-listedit-description', 'wpCollabDescTextbox', $parts[0], $attribs );
		$html .= Html::openElement( 'div', [ 'class' => 'mw-collabkit-textboxmain' ] );
		$html .= self::editorInput( 'label', 'mw-collabkit-list', 'collaborationkit-listedit-list', 'wpTextbox1' );

		$out = RequestContext::getMain()->getOutput();
		$out->addHtml( $html );

		$out->addHtml( Html::Hidden( 'wpCollaborationKitOptions', $parts[1] ) );

		$this->showTextbox1( null, trim( $parts[2] ) );
		$out->addHtml( Html::closeElement( 'div' ) . Html::closeElement( 'div' ) );
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

	/**
	 * Helper for generatng random form elements for the extended edit pages
	 *
	 * @param $type string
	 * @param $id string
	 * @param $label string
	 * @param $name string
	 * @param $value string|bool starting value
	 * @param $attribs array $attribs for Html functions
	 * @param $options array options for select, radio types
	 *
	 * @return string html
	 */
	public static function editorInput( $type, $id, $label, $name, $value = '', $attribs = [], $options = [] ) {
		if ( $type == 'check' ) {
			$html = '';
		} else {
			$html = Html::label( wfMessage( $label )->text(), $name );
		}

		switch ( $type ) {
			case 'label':
				return $html;
			case 'input':
				$html .= 	Html::input( $name,  $value, 'text', $attribs );
				break;
			case 'textarea':
				$html .= 	Html::textarea( $name, $value, $attribs );
				break;
			case 'check':
				$html .= 	Html::check( $name, $value ? true : false, $attribs );
				$html = Html::label( wfMessage( $label )->text(), $name );
				break;
			case 'select':
				$attribs['name'] = $name;
				$attribs['id'] = $id;
				$html .= Html::openElement( 'select', $attribs );
				foreach ( $options as $message => $optionValue ) {
					$optionAttribs = [ 'value' => $optionValue ];
					if ( $optionValue == $value ) {
						$optionAttribs['selected'] = '';
					}
					$html .= Html::rawElement( 'option', $optionAttribs, wfMessage( $message )->text() );
				}
				$html .= Html::closeElement( 'select' );
				break;
			case 'radio':
				$html = 'not implemented';
				break;
			default:
				throw new Exception( "don't do that" );
		}

		$html = Html::rawElement( 'div', [ 'class' => $id ], $html );
		return $html;
	}
}
