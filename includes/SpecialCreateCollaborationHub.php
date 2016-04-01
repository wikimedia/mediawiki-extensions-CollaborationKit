<?php

/**
 * Form to create Collaboration Hubs (and maybe even migrate them later, but not yet)
 * Based on code from MassMessage
 *
 * @file
 */

class SpecialCreateCollaborationHub extends SpecialPage {

	public function __construct() {
		parent::__construct( 'CreateCollaborationHub' );
	}

	/*
	description of page
	inputbox for a name
		go: check if it already exists
			yes & is wikitext:
				warn that it already exists with link
				inputbox for new pagename for archive; button to convert
					go: dump 'em on special:editcollaborationhub with some prefilled (possibly invisible) items

			yes & is collaborationhub: warn that it already exists with link; display original inputbox

			no: dump 'em on special:editcollaborationhub with some prefilled (possibly invisible) items
	*/

}
