<?php

// Hooks and crap
class CollaborationKitHooks {

	/**
	 * Some extra tabs for editing
	 * @param &$sktemplate SkinTemplate
	 * @param &$links array
	 * @return bool
	 */
	public static function onSkinTemplateNavigation( &$sktemplate, &$links ) {
		$title = $sktemplate->getTitle();
		$request = $sktemplate->getRequest();
		if ( isset( $links['views']['edit'] ) ) {
			if ( $title->hasContentModel( 'CollaborationListContent' ) || $title->hasContentModel( 'CollaborationHubContent' ) ) {
				// Edit as JSON
				$active = in_array( $request->getVal( 'action' ), [ 'edit', 'submit' ] )
					&& $request->getVal( 'format' ) === 'application/json';
				$links['actions']['editasjson'] = [
					'class' => $active ? 'selected' : false,
					'href' => wfAppendQuery(
						$links['views']['edit']['href'],
						[ 'format' => 'application/json' ]
					),
					'text' => wfMessage( 'collaborationkit-editjsontab' )->text()
				];
				if ( $active ) {
					// Make it not be selected when editing json.
					$links['views']['edit']['class'] = false;
				}
			}
			if ( $title->hasContentModel( 'CollaborationHubContent' ) ) {
				// Add feature
				$links['actions']['addnewfeature'] = [
					'class' => '',
					'href' => SpecialPage::getTitleFor( 'CreateHubFeature' )->getFullUrl( [ 'collaborationhub' => $title->getFullText() ] ),
					'text' => wfMessage( 'collaborationkit-hub-addpage' )->text()
				];
			}
		}
		return true;
	}

	/**
	 * TODO DOCUMENT I'M SURE THIS IS IMPORTANT, BUT I HAVE NO IDEA WHY OR WHAT FOR
	 *
	 * @param $parser Parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'transcludelist', 'CollaborationListContent::transcludeHook' );
		// Hack for transclusion.
		$parser->setHook( 'collaborationkitloadliststyles', 'CollaborationListContent::loadStyles' );
	}

	/**
	 * Declares JSON as the code editor language for CollaborationKit pages.
	 *
	 * This hook only runs if the CodeEditor extension is enabled.
	 * @param $title Title
	 * @param &$lang string Page language.
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		$contentModel = $title->getContentModel();
		$ckitModels = [ 'CollaborationHubContent', 'CollaborationListContent' ];
		$req = RequestContext::getMain()->getRequest();
		// Kind of hacky use of globals.
		if ( $contentModel === 'CollaborationListContent' ) {
			if ( $req->getVal( 'format' ) === 'application/json' ) {
				$lang = 'json';
				return true;
			} else {
				// JsonConfig incorrectly triggers on anything with the default
				// format of application/json, which includes us. false is kind
				// of hacky but only way to stop it.
				$lang = null;
				return false;
			}
		}
		if ( $contentModel === 'CollaborationHubContent' ) {
			$lang = 'json';
			return true;
		}
	}

	/**
	 * For the table of contents on subpages of a CollaborationHub
	 *
	 * @param $out OutputPage
	 * @param $text string the HTML text to be added
	 * @return bool
	 */
	public static function onOutputPageBeforeHTML( &$out, &$text ) {
		$title = $out->getTitle();
		$parentHub = CollaborationHubContent::getParentHub( $title );

		if ( isset( $parentHub ) && !$out->getProperty( 'CollaborationHubSubpage' ) ) {
			$toc = new CollaborationHubTOC();
			$out->prependHtml( $toc->renderSubpageToC( $parentHub ) );

			$colour = Revision::newFromTitle( $parentHub )->getContent()->getThemeColour();
			$text = Html::rawElement( 'div', [ 'class' => "mw-cklist-square-$colour" ], $text );

			$out->addModuleStyles( 'ext.CollaborationKit.hubsubpage.styles' );
			$out->addModules( 'ext.CollaborationKit.icons' );
			$out->addModules( 'ext.CollaborationKit.blots' );

			// Set this mostly just so we can make sure this entire thing hasn't already been done, because otherwise the ToC is added twice on edit for some reason
			$out->setProperty( 'CollaborationHubSubpage', true );
		}
		return true;
	}
}
