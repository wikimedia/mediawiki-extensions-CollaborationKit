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
		if ( $title->hasContentModel( 'CollaborationHubContent' )
			&& array_key_exists( 'edit', $links['views'] )
		) {
			// Get the revision being viewed, if applicable
			$request = $sktemplate->getRequest();
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
		$ourFiles = array();
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
	static function onCodeEditorGetPageLanguage( $title, &$lang ) {
		$contentModel = $title->getContentModel();
		$ckitModels = [ 'CollaborationHubContent', 'CollaborationListContent' ];
		if ( in_array( $contentModel, $ckitModels ) ) {
			$lang = 'json';
			return true;
		}
	}
}
