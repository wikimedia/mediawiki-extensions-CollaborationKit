<?php


// Placeholder junk
// This might turn into something. I kind of doubt it.
class CollaborationKit {

	// ...
}

// Hooks and crap
class CollaborationKitHooks {

	/**
	 * Override the Edit tab for for CollaborationHub pages; stolen from massmessage
	 * @param SkinTemplate &$sktemplate
	 * @param array &$links
	 * @return bool
	 */
	public static function onSkinTemplateNavigation( &$sktemplate, &$links ) {
		$title = $sktemplate->getTitle();
		$request = $sktemplate->getRequest();
		if ( isset( $links['views']['edit'] ) ) {
			if ( $title->hasContentModel( 'CollaborationHubContent' ) ) {
				// Get the revision being viewed, if applicable
				$direction = $request->getVal( 'direction' );
				$diff = $request->getVal( 'diff' );
				$oldid = $request->getInt( 'oldid' ); // Guaranteed to be an integer, 0 if invalid
				if ( $direction === 'next' && $oldid > 0 ) {
					$next = $title->getNextRevisionId( $oldid );
					$revId = ( $next ) ? $next : $oldid;
				} elseif ( $direction === 'prev' && $oldid > 0 ) {
					$prev = $title->getPreviousRevisionId( $oldid );
					$revId = ( $prev ) ? $prev : $oldid;
				} elseif ( $diff !== null ) {
					if ( ctype_digit( $diff ) ) {
						$revId = (int)$diff;
					} elseif ( $diff === 'next' && $oldid > 0 ) {
						$next = $title->getNextRevisionId( $oldid );
						$revId = ( $next ) ? $next : $oldid;
					} else { // diff is 'prev' or gibberish
						$revId = $oldid;
					}
				} else {
					$revId = $oldid;
				}

				$query = ( $revId > 0 ) ? 'oldid=' . $revId : '';
				$links['views']['edit']['href'] = SpecialPage::getTitleFor(
					'EditCollaborationHub', $title
				)->getFullUrl( $query );
			} elseif ( $title->hasContentModel( 'CollaborationListContent' ) ) {
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

	/**
	 * Register our tests with PHPUnit
	 *
	 * Shamelessly stolen from MassMessage
	 *
	 * @param &$files Array List of files for PHPUnit
	 */
	public static function onUnitTestsList( &$files ) {
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/../tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = [];
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );
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
