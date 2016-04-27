<?php

/**
 * Form to create Collaboration Hubs + migrations/imports/clones
 * Based on code from MassMessage
 *
 * @file
 */

class SpecialCreateCollaborationHub extends FormSpecialPage {

	public function __construct() {
		parent::__construct( 'CreateCollaborationHub' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->getOutput()->addModules( 'ext.CollaborationKit.edit' );
		parent::execute( $par );
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		// TODO: Do an actual check based on stuff (page type not already set to 'main' with autofill, is an actual subpage)
		// If this isn't possible, move this logic to js (but it should be?)

		// We know it's a subpage, so ignore mainpage options
		$isSubpage = false;
		// We know it's a mainpage, so ignore subpage options
		$isMainpage = true;

		$fields = array(
			// autofilled from how they got here, hopefully
			'title' => array(
				'type' => 'text',
				'label-message' => 'collaborationkit-create-title',
			),
			// Display name can be different from page title
			'display_name' => array(
				'type' => 'text',
				'label-message' => 'collaborationkit-create-page-name',
			)
		);

		// Content source options
		$fields['content_source'] = array(
			'type' => 'select',
			'options' => $this->getOptions( array(
				'collaborationhub-create-new' => 'new',
				'collaborationhub-create-import' => 'import',
				'collaborationhub-create-clone' => 'clone',
			) ),
			'default' => 'new', // might want to change to clone from the default (TODO add a canned default as example and stuff)
			'label-message' => 'collaborationkit-create-content',
		);
		$fields['source'] = array(
			'type' => 'text',
			'label-message' => 'collaborationkit-create-source',
		);

		$fields['description'] = array(
			'type' => 'textarea',
			'rows' => 5,
			'label-message' => 'collaborationkit-edit-description',
		);

		return $fields;
	}

	/**
	 * Build and return the aossociative array for the content source field.
	 * @return array
	 */
	protected function getOptions( $mapping ) {
		$options = array();
		foreach ( $mapping as $msgKey => $option ) {
			$options[$this->msg( $msgKey )->escaped()] = $option;
		}
		return $options;
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		$title = Title::newFromText( $data['title'] );
		if ( !$title ) {
			return Status::newFatal( 'collaborationhub-create-invalidtitle' );
		} elseif ( $title->exists() ) {
			// TODO: Option to import it to itself as target
			return Status::newFatal( 'collaborationhub-create-exists' );
		} elseif (
			!$title->userCan( 'edit' ) ||
			!$title->userCan( 'create' ) ||
			!$title->userCan( 'editcontentmodel' )
		) {
			return Status::newFatal( 'collaborationhub-create-nopermission' );
		}

		$content = array();

		// ACTUAL STUFF HERE
		if ( $data['content_source'] !== 'new' ) { // Importing from wikitext
			$source = Title::newFromText( $data['source'] );
			if ( !$source ) {
				return Status::newFatal( 'collaborationhub-create-invalidsource' );
			}

			if ( $data['content_source'] === 'clone' ) {
				// Copy another hub
				// Just copy some of the bits...

				// TODO prefill the actual content
			} elseif ( $data['content_source'] === 'import' ) {
				// Do some magic based on the source:
				// If wikiproject x project: get module list, recreate modules
				// If regular page: pull headers

				// TODO prefill the actual content
			}
			// Render preview
		} else {

			// ...?
		}

		$title = Title::newFromText( $data['title'] );
		if ( !$title ) {
			return Status::newFatal( 'collaborationhub-create-invalidtitle' );
		}

		$result = CollaborationHubContentHandler::edit(
			$title,
			$data['display_name'],
			'main',
			'subpage-list',
			$data['description'],
			$content,
			$this->msg( 'collaborationhub-create-editsummary' )->inContentLanguage()->plain(),
			$this->getContext()
		);

		if ( !$result->isGood() ) {
			return $result;
		}

		$this->getOutput()->redirect( $title->getFullUrl() );
		return Status::newGood();
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * Set the form format to div instead of table
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setDisplayFormat( 'div' );
	}

	// Hide source input unless actually providing a source (not 'new')
	// Autofill displayname based on title (same as title minus namespace by default)
	// ...?

}
