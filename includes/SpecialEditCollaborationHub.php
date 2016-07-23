<?php

/*
 * Shamelessly stolen from MassMessage. This is thus whatever that was.
 */

class SpecialEditCollaborationHub extends FormSpecialPage {

	/**
	 * The title of the list to edit
	 * If not null, the title refers to a delivery list.
	 * @var Title|null
	 */
	protected $title;

	/**
	 * The revision to edit
	 * If not null, the user can edit the delivery list.
	 * @var Revision|null
	 */
	protected $rev;

	/**
	 * The content type of the revision content content
	 * Determines how it's edited and parsed within the overall json.
	 * @var string
	 */
	protected $contentType;

	/**
	 * The message key for the error encountered while parsing the title, if any
	 * @var string|null
	 */
	protected $errorMsgKey;

	public function __construct() {
		parent::__construct( 'EditCollaborationHub' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->getOutput()->addModules( 'ext.CollaborationKit.edit' );
		parent::execute( $par );
	}

	/**
	 * @param string $par
	 */
	protected function setParameter( $par ) {
		if ( $par === null || $par === '' ) {
			$this->errorMsgKey = 'collaborationkit-edit-invalidtitle';
		} else {
			$title = Title::newFromText( $par );

			if ( !$title
				|| !$title->exists()
				|| !$title->hasContentModel( 'CollaborationHubContent' )
			) {
				$this->errorMsgKey = 'collaborationkit-edit-invalidtitle';
				// TODO: Make this handle non-existent pages to create and edit them
			} else {
				$this->title = $title;

				if ( !$title->userCan( 'edit' ) ) {
					$this->errorMsgKey = 'collaborationkit-edit-nopermission';
				} else {
					$revId = $this->getRequest()->getInt( 'oldid' );
					if ( $revId > 0 ) {
						$rev = Revision::newFromId( $revId );
						if ( $rev
							&& $rev->getTitle()->equals( $title )
							&& $rev->getContentModel() === 'CollaborationHubContent'
							&& $rev->userCan( Revision::DELETED_TEXT, $this->getUser() )
						) {
							$this->rev = $rev;
						} else { // Use the latest revision for the title if $rev is invalid.
							$this->rev = Revision::newFromTitle( $title );
						}
					} else {
						$this->rev = Revision::newFromTitle( $title );
					}
				}
			}
		}
	}

	/**
	 * Override the parent implementation to modify the page title and add a backlink.
	 */
	public function setHeaders() {
		parent::setHeaders();
		if ( $this->title ) {
			$out = $this->getOutput();

			// Page title
			$out->setPageTitle(
				$this->msg( 'collaborationkit-edit-pagetitle', $this->title->getPrefixedText() )
			);
			$this->getSkin()->setRelevantTitle( $this->title );

			// Backlink
			if ( $this->rev ) {
				$revId = $this->rev->getId();
				$query = ( $revId !== $this->title->getLatestRevId() ) ?
					[ 'oldid' => $revId ] : [];
			} else {
				$query = [];
			}
			$out->addBacklinkSubtitle( $this->title, $query );

			// Edit notices; modified from EditPage::showHeader()
			if ( $this->rev ) {
				$out->addHTML(
					implode( "\n", $this->title->getEditNotices( $this->rev->getId() ) )
				);
			}

			// Protection warnings; modified from EditPage::showHeader()
			if ( $this->title->isProtected( 'edit' )
				&& MWNamespace::getRestrictionLevels( $this->title->getNamespace() ) !== [ '' ]
			) {
				if ( $this->title->isSemiProtected() ) {
					$noticeMsg = 'semiprotectedpagewarning';
				} else { // Full protection
					$noticeMsg = 'protectedpagewarning';
				}
				LogEventsList::showLogExtract( $out, 'protect', $this->title, '',
					[ 'lim' => 1, 'msgKey' => [ $noticeMsg ] ] );
			}
		}
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {
		// Return an empty form if the title is invalid or if the user can't edit the list.
		if ( !$this->rev ) {
			return [];
		}

		$pageContent = $this->rev->getContent( Revision::FOR_THIS_USER, $this->getUser() );
		$pageName = $pageContent->getPageName();
		$description = $pageContent->getDescription();
		$pageType = $pageContent->getPageType();
		$contentType = $pageContent->getContentType();
		$content = $pageContent->getContent();
		$icon = $pageContent->getIcon();
		$colour = $pageContent->getThemeColour();

		// Get the more complicated bits
		if ( $contentType !== 'wikitext' ) {
			// Convert json to pseudowikitext
			$content = $this->makeListReadable( $content, $contentType );
		}

		$formJunk = [];

		// Check which label to use for page_name
		if ( $pageType == 'main' ) {
			$nameLabel = 'collaborationkit-edit-hub-name';
			$iconLabel = 'collaborationjit-edit-hub-icon';
		} else {
			$nameLabel = 'collaborationkit-edit-page-name';
			$iconLabel = 'collaborationjit-edit-page-icon';
		}
		$formJunk['page_name'] = [
			'type' => 'text',
			'maxlength' => 255,
			'size' => 40,
			'default' => ( $pageName !== null ) ? $pageName : '',
			'label-message' => $nameLabel,
			'cssclass' => 'ext-ck-edit-pagename',
		];

		// Skip implicit type on main
		if ( $pageType != 'main' && $pageType != 'userlist' ) {
			$typesList = [];
			foreach ( $pageContent->getPossibleTypes() as $type ) {
				$typeString = $this->msg( 'collaborationhub-display-' . $type )->parse();
				$typesList[$typeString] = $type;
			}
			$formJunk['page_display_type'] = [
				'type' => 'select',
				'options' => $typesList,
				'default' => $contentType,
				'label-message' => 'collaborationkit-edit-page-type',
				'cssclass' => 'ext-ck-edit-pagetype',
			];
		} else {
			$formJunk['page_display_type'] = [
				'type' => 'text',
				'default' => $pageType,
				'label-message' => 'collaborationkit-edit-page-type',
				'cssclass' => 'ext-ck-edit-pagetype hidden',
			];
		}

		$formJunk['icon'] = [
			'type' => 'text',
			'default' => $icon,
			'maxlength' => 255,
			'size' => 50,
			'label-message' => $iconLabel,
			'cssclass' => 'ext-ck-edit-icon',
		];
		$formJunk['colour'] = [
			'type' => 'text',
			'default' => $colour,
			'maxlength' => 255,
			'size' => 50,
			'label-message' => $colourLabel,
			'cssclass' => 'ext-ck-edit-colour',
		];
		$formJunk['description'] = [
			'type' => 'textarea',
			'rows' => 4,
			'default' => ( $description !== null ) ? $description : '',
			'label-message' => 'collaborationkit-edit-description',
			'cssclass' => 'ext-ck-edit-description',
		];
		// This is stupid. They all get handled in a textarea.
		// Also need some type for multiple lists on the page.
		$formJunk['content'] = [
			'type' => 'textarea',
			'default' => $content,
			'label-message' => 'collaborationkit-edit-content',
			'cssclass' => 'ext-ck-edit-content',
		];
		$formJunk['summary'] = [
			'type' => 'text',
			'maxlength' => 255,
			'size' => 50,
			'label-message' => 'collaborationkit-edit-summary',
			'cssclass' => 'ext-ck-edit-summary',
		];

		return $formJunk;
	}

	/**
	 * Hide the form if the title is invalid or if the user can't edit the list.
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		if ( !$this->rev ) {
			$form->setWrapperLegend( false );
			$form->suppressDefaultSubmit( true );
		}
		$form->setDisplayFormat( 'div' );
		$form->setWrapperLegend( false );
	}

	/**
	 * Return instructions for the form and / or warnings.
	 * @return string
	 */
	protected function preText() {
		if ( $this->rev ) {
			// Instructions
			$headerKey = 'collaborationkit-edit-header';
			$html = Html::rawElement( 'p', [], $this->msg( $headerKey )->parse() );

			// Deleted revision warning
			if ( $this->rev->isDeleted( Revision::DELETED_TEXT ) ) {
				$html .= Html::openElement( 'div', [ 'class' => 'mw-warning plainlinks' ] );
				$html .= Html::rawElement( 'p', [],
					$this->msg( 'rev-deleted-text-view' )->parse() );
				$html .= Html::closeElement( 'div' );
			}

			// Old revision warning
			if ( $this->rev->getId() !== $this->title->getLatestRevID() ) {
				$html .= Html::rawElement( 'p', [], $this->msg( 'editingold' )->parse() );
			}
		} else {
			// Error determined in setParameter()
			$html = Html::rawElement( 'p', [], $this->msg( $this->errorMsgKey )->parse() );
		}
		return $html;
	}

	/**
	 * Return a copyright warning to be displayed below the form.
	 * @return string
	 */
	protected function postText() {
		if ( $this->rev ) {
			return EditPage::getCopyrightWarning( $this->title, 'parse' );
		} else {
			return '';
		}
	}

	/**
	 * @param array $data
	 * @param HTMLForm $form
	 * @return Status
	 */
	public function onSubmit( array $data, HTMLForm $form = null ) {
		if ( !$this->title ) {
			return Status::newFatal( 'collaborationkit-edit-invalidtitle' );
		}

		// get the page type because it may or may not be in the form - form only has them if not implicit (or should)
		// page type can be pulled from what it was previously, or can be assumed if nothing was ever set to be wikitext
		if ( !isset( $data['page_display_type'] ) ) {
			$pageType = '';
			if ( !$this->rev ) {
				$contentType = 'wikitext';
			} else {
				$pageContent = $this->rev->getContent( Revision::FOR_THIS_USER, $this->getUser() );
				$contentType = $pageContent->getContentType();
			}
		} else {
			$pageType = $data['page_display_type'];
			if ( $pageType == 'main' ) {
				$contentType = 'subpage-list';
			} elseif ( $pageType =='userlist' ) {
				$contentType = 'icon-list';
			} elseif ( $pageType =='wikitext' ) {
				$contentType = 'wikitext';
			} else {
				$contentType = $pageType;
				$pageType = '';
			}
		}

		// Parse input into target array.
		if ( $contentType == 'wikitext' ) {
			$content = $data['content'];
		} else {
			$content = self::makeListSensible( $data['content'], $contentType );
		}

		// Blank edit summary warning
		if ( $data['summary'] === ''
			&& $this->getUser()->getOption( 'forceeditsummary' )
			&& $this->getRequest()->getVal( 'summarywarned' ) === null
		) {
			$form->addHiddenField( 'summarywarned', 'true' );
			return Status::newFatal( $this->msg( 'collaborationkit-edit-missingsummary' ) );
		}

		$editResult = CollaborationHubContentHandler::edit(
			$this->title,
			$data['page_name'],
			$data['icon'],
			$data['colour'],
			$pageType,
			$contentType,
			$data['description'],
			$content,
			$data['summary'],
			$this->getContext()
		);

		if ( !$editResult->isGood() ) {
			return $editResult;
		}

		$this->getOutput()->redirect( $this->title->getFullUrl() );
		return Status::newGood();
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * Parse list json array into a psuedo wikitext list
	 * This should be editable by users, but will result in a lot of explosions
	 * when slightly messed up.
	 * @param array $content
	 * @return string
	 */
	protected static function makeListReadable( $content, $contentType ) {
		$output = '';
		if ( $contentType == 'icon-list' || $contentType == 'block-list' ) {
			foreach ( $content as $item ) {
				if ( isset( $item['item'] ) ) {
					$output .= '* ' . $item['item'];
					if ( isset( $item['icon'] ) ) {
						$output .= "\n*:: " . $item['icon'];
					}
					if ( isset( $item['notes'] ) ) {
						$output .= "\n" . $item['notes'];
					}
					$output .= "\n\n";
				}
			}
		} elseif ( $contentType == 'list' || $contentType == 'subpage-list' ) {
			foreach ( $content as $item ) {
				if ( isset( $item['item'] ) ) {
					$output .= $item['item'] . "\n";
				}
			}
		}
		return $output;
	}

	/**
	 * Parse psuedo wikitext list back into a json-compatible array
	 * This undoes makeListReadable.
	 * @param string
	 * @return array $content
	 */
	protected static function makeListSensible( $content, $contentType ) {
		$output = [];
		if ( $contentType == 'list' || $contentType == 'subpage-list' ) {
			$lines = array_filter( explode( "\n", $content ), 'trim' ); // Array of non-empty lines
			foreach ( $lines as $line ) {
				$output[] = [
					'item' => $line,
					'icon' => null, // TODO remove nulls out of things that don't actually need them (make output cleaner)
					'notes' => null
				];
			}
		} elseif ( $contentType == 'icon-list' || $contentType == 'block-list' ) {
			array_filter( $unhandledList = explode( "\n\n", $content ), 'trim' );
			foreach ( $unhandledList as $item ) {
				$pattern = "/^\\*\s*(.*)(?:\n\\*::\s*(.*)\n)?(?:\s*(.*))?$/m";
				preg_match_all( $pattern, $item, $out );

				$output[] = [
					'item' => is_array( $out[1] ) ? $out[1][0] : null,
					'icon' => is_array( $out[2] ) ? $out[2][0] : null,
					'notes' => is_array( $out[3] ) ? $out[3][0] : null,
				];
			}
		}
		return $output;
	}
}
