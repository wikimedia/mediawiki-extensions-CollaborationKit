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

		if ( $parentHub
			&& $out->getProperty( 'CollaborationHubSubpage' ) === 'in-progress'
		) {
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

	/**
	 * Propogate if we should create a ToC from ParserOutput -> OutputPage.
	 *
	 * Its assumed we should create a ToC on the following conditions:
	 *  * __NOCOLLABORATIONHUBTOC__ is not on page
	 *  * Somebody added parser metadata to the OutputPage (More likely to be a real page)
	 *  * The limit report was enabled (More likely to be a real page)
	 * These conditions are hacky. Ideally we'd come up with a more
	 * robust way of determining if this is really a wikipage.
	 *
	 * Eventually, the TOC will be output by onOutputPageBeforeHTML hook if this
	 * hook signals it is ok.
	 *
	 * @param $out OutputPage
	 * @param $pout ParserOutput
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $pout ) {
		if ( $out->getProperty( 'CollaborationHubSubpage' ) ) {
			// We've already been here, so we can't abort
			// outputting the TOC at this stage.
			wfDebug( __METHOD__ . ' TOC already outputted, possibly incorrectly.' );
			return;
		}

		if ( $pout->getProperty( 'nocollaborationhubtoc' ) !== false ) {
			// TOC disabled, mark as done.
			$out->setProperty( 'CollaborationHubSubpage', true );
		} elseif ( $pout->getLimitReportData() ) {
			$out->setProperty( 'CollaborationHubSubpage', "in-progress" );
		}
	}
	/**
	 * Register __NOCOLLABORATIONHUBTOC__ as a magic word.
	 *
	 * @param &$magickWords Array All double underscore magic ids
	 */
	public static function onGetDoubleUnderscoreIDs( array &$magicWords ) {
		$magicWords[] = 'nocollaborationhubtoc';
	}
}
