<?php

// Hooks and crap
class CollaborationKitHooks {

	/**
	 * Override the Edit tab for for CollaborationHub pages; stolen from massmessage
	 * @param &$sktemplate SkinTemplate
	 * @param &$links array
	 * @return bool
	 */
	public static function onSkinTemplateNavigation( &$sktemplate, &$links ) {
		$title = $sktemplate->getTitle();
		$request = $sktemplate->getRequest();
		if ( isset( $links['views']['edit'] ) ) {
			if ( $title->hasContentModel( 'CollaborationListContent' ) ) {
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
		}
		return true;
	}

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

}
