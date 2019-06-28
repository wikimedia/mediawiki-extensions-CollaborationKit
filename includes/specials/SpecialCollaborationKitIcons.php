<?php

/**
 * Special page that generates a list of icons and corresponding icon names.
 *
 * @file
 */

class SpecialCollaborationKitIcons extends IncludableSpecialPage {

	public function __construct() {
		parent::__construct( 'CollaborationKitIcons' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$output = $this->getOutput();
		$output->addModuleStyles( [
			'ext.CollaborationKit.hub.styles',
			'ext.CollaborationKit.icons',
			'ext.CollaborationKit.blots'
		] );
		$this->setHeaders();

		$icons = CollaborationKitImage::getCannedIcons();

		// Intro
		$wikitext = $this->msg( 'collaborationkit-iconlist-intro' )->plain();
		$wikitext .= "\n";

		// Table header
		$wikitext .= "{| class='wikitable'\n";
		$wikitext .= "|-\n";
		$wikitext .= '! ' .
					 $this->msg( 'collaborationkit-iconlist-columnheader-icon' )->plain() .
					 "\n";
		$wikitext .= '! ' .
					 $this->msg( 'collaborationkit-iconlist-columnheader-iconname' )->plain() .
					 "\n";
		$wikitext .= "|-\n";

		// Iterate through each icon and generate a row
		$iconCount = count( $icons );
		for ( $i = 0; $i < $iconCount; $i++ ) {
			$imageCode = CollaborationKitImage::makeImage(
				$icons[$i],
				60,
				[ 'renderAsWikitext' => true, 'link' => false ]
			);
			$wikitext .= '| ' . $imageCode . "\n";
			$wikitext .= '| ' . $icons[$i] . "\n";
			$wikitext .= "|-\n";
		}

		// End table
		$wikitext .= '|}';

		$output->addWikiTextAsInterface( $wikitext );
	}
}
