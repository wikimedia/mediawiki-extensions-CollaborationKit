<?php

// Hooks and crap
class CollaborationKitHooks {

	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'transcludelist', 'CollaborationListContent::transcludeHook' );
		// Hack for transclusion.
		$parser->setHook( 'collaborationkitloadliststyles', 'CollaborationListContent::loadStyles' );
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
