<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

/**
 * Hooks to modify the default behavior of MediaWiki.
 *
 * @file
 */

class CollaborationKitHooks {

	/**
	 * Some extra tabs for editing
	 *
	 * @param SkinTemplate &$sktemplate
	 * @param array &$links
	 * @return bool
	 */
	public static function onSkinTemplateNavigation( &$sktemplate, &$links ) {
		$title = $sktemplate->getTitle();
		$request = $sktemplate->getRequest();
		if ( isset( $links['views']['edit'] ) ) {
			if ( $title->hasContentModel( 'CollaborationListContent' )
				|| $title->hasContentModel( 'CollaborationHubContent' )
			) {
				// Edit as JSON
				$active = in_array(
					$request->getVal( 'action' ),
					[ 'edit', 'submit' ]
				) && $request->getVal( 'format' ) === 'application/json';
				$links['actions']['editasjson'] = [
					'class' => $active ? 'selected' : false,
					'href' => wfAppendQuery(
						$links['views']['edit']['href'],
						[ 'format' => 'application/json' ]
					),
					'text' => wfMessage( 'collaborationkit-editjsontab' )
						->text()
				];
				if ( $active ) {
					// Make it not be selected when editing json.
					$links['views']['edit']['class'] = false;
				}
			}
			if ( !in_array( $request->getVal( 'action' ), [ 'edit', 'submit' ] )
				&& $title->hasContentModel( 'CollaborationHubContent' )
			) {
				// Add feature
				$links['actions']['addnewfeature'] = [
					'class' => '',
					'href' => SpecialPage::getTitleFor( 'CreateHubFeature' )
						->getFullURL( [ 'collaborationhub' => $title->getFullText() ] ),
					'text' => wfMessage( 'collaborationkit-hub-addpage' )->text()
				];
			}
		}
		return true;
	}

	/**
	 * Register the {{#transcludelist:...}} and <collaborationkitloadliststyles>
	 * hooks
	 *
	 * #transcludelist is to allow users to transclude a CollaborationList with
	 * custom options. <collaborationkitloadliststyles> allows enabling our style
	 * modules directly from wikitext, so we can do plain wikitext transclusions of
	 * lists and have them work properly, since ContentHandler does not provide access
	 * to the parser when doing plain wikitext transclusion.
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'transcludelist',
			'CollaborationListContent::transcludeHook'
		);
		// Hack for transclusion.
		$parser->setHook(
			'collaborationkitloadliststyles',
			'CollaborationListContent::loadStyles'
		);
	}

	/**
	 * Declares JSON as the code editor language for CollaborationKit pages.
	 *
	 * This hook only runs if the CodeEditor extension is enabled.
	 * @param Title $title
	 * @param string &$lang Page language.
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		$contentModel = $title->getContentModel();
		$ckitModels = [ 'CollaborationHubContent', 'CollaborationListContent' ];
		$req = RequestContext::getMain()->getRequest();
		// Kind of hacky use of globals.
		if ( in_array( $contentModel, $ckitModels ) ) {
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
	}

	/**
	 * For the table of contents on subpages of a CollaborationHub
	 *
	 * @param OutputPage $out
	 * @param string &$text the HTML text to be added
	 * @return bool
	 */
	public static function onOutputPageBeforeHTML( OutputPage $out, &$text ) {
		$title = $out->getTitle();
		$parentHub = CollaborationHubContent::getParentHub( $title );

		if ( !$parentHub
			|| $out->getProperty( 'CollaborationHubSubpage' ) !== 'in-progress'
		) {
			return true;
		}

		/** @var CollaborationHubContent $revisionContent */
		$revisionContent = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $parentHub )
			->getContent( SlotRecord::MAIN );
		if ( count( $revisionContent->getContent() ) > 0 ) {
			$toc = new CollaborationHubTOC();
			$out->prependHTML( $toc->renderSubpageToC( $parentHub ) );

			$colour = $revisionContent->getThemeColour();
			$text = Html::rawElement(
				'div',
				[ 'class' => "mw-cklist-square-$colour" ],
				$text
			);

			$out->addModuleStyles( [
				'ext.CollaborationKit.hubsubpage.styles',
				'ext.CollaborationKit.icons',
				'ext.CollaborationKit.blots'
			] );

			// Set this mostly just so we can make sure this entire thing hasn't
			// already been done, because otherwise the ToC is added twice on
			// edit for some reason
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
	 * @param OutputPage $out
	 * @param ParserOutput $pout
	 */
	public static function onOutputPageParserOutput( OutputPage $out,
		ParserOutput $pout
	) {
		if ( $out->getProperty( 'CollaborationHubSubpage' ) ) {
			// We've already been here, so we can't abort
			// outputting the TOC at this stage.
			wfDebug( __METHOD__ . ' TOC already outputted, possibly incorrectly.' );
			return;
		}

		if ( $pout->getPageProperty( 'nocollaborationhubtoc' ) !== null ) {
			// TOC disabled, mark as done.
			$out->setProperty( 'CollaborationHubSubpage', true );
		} elseif ( $pout->getLimitReportData() ) {
			$out->setProperty( 'CollaborationHubSubpage', 'in-progress' );
		}
	}

	/**
	 * Register __NOCOLLABORATIONHUBTOC__ as a magic word.
	 *
	 * @param array &$magicWords All double underscore magic ids
	 */
	public static function onGetDoubleUnderscoreIDs( array &$magicWords ) {
		$magicWords[] = 'nocollaborationhubtoc';
	}
}
