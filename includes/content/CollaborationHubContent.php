<?php
/**
 * A content model for group collaboration pages.
 *
 * The principle behind CollaborationHubContent is to facilitate
 * the development of "WikiProjects," called "Portals" on other
 * wikis. CollaborationHubContent facilitates the development
 * of these nodes of activity, consisting of header content, a
 * table of contents, and several transcluded pages.
 * Schema is found in CollaborationHubContentSchema.php.
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

/**
 * @class CollaborationHubContent
 */
class CollaborationHubContent extends JsonContent {

	/** @var string */
	protected $displayName;

	/** @var string */
	protected $image;

	/** @var string */
	protected $introduction;

	/** @var array pages included in the hub */
	protected $content;

	/** @var string */
	protected $footer;

	/** @var string */
	protected $themeColour;

	/** @var $displaymode String How to display contents */
	protected $displaymode;

	/** @var bool Whether contents have been populated */
	protected $decoded = false;

	/** @var string Error message text */
	protected $errortext;

	/**
	 * 23 preset colours; actual colour values are set in the extension.json and
	 * less modules
	 *
	 * @return array
	 */
	public static function getThemeColours() {
		return [
			'darkred',
			'red',
			'darkgrey',
			'lightgrey',
			'skyblue',
			'blue',
			'bluegrey',
			'navyblue',
			'darkblue',
			'aquamarine',
			'violet',
			'purple',
			'mauve',
			'lightmauve',
			'salmon',
			'orange',
			'yellow',
			'gold',
			'pastelyellow',
			'forestgreen',
			'brightgreen',
			'khaki',
			'black'
		];
	}

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, 'CollaborationHubContent' );
	}

	/**
	 * Decode and validate the contents
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		$hubSchema = include __DIR__ . '/CollaborationHubContentSchema.php';
		$jsonParse = $this->getData();
		if ( $jsonParse->isGood() ) {
			// TODO: The schema should be checking for required fields but for
			// some reason that doesn't work
			if ( !isset( $jsonParse->value->content ) ) {
				return false;
			}
			// Forcing the object to become an array
			$jsonAsArray = json_decode(
				json_encode( $jsonParse->getValue() ), true );
			try {
				EventLogging::schemaValidate( $jsonAsArray, $hubSchema );
				return true;
			} catch ( JsonSchemaException $e ) {
				return false;
			}
		}
		return false;
	}

	/**
	 * Decode the JSON contents and populate protected variables
	 */
	protected function decode() {
		if ( $this->decoded ) {
			return;
		}
		$jsonParse = $this->getData();
		$data = $jsonParse->isGood() ? $jsonParse->getValue() : null;
		if ( $data ) {
			if ( !$this->isValid() ) {
				$this->displaymode = 'error';
				if ( !parent::isValid() ) {
					// It's not even valid json
					$this->errortext = htmlspecialchars(
						$this->getText()
					);
				} else {
					$this->errortext = FormatJson::encode(
						$data,
						true,
						FormatJson::ALL_OK
					);
				}
			} else {
				$this->displayName = $data->display_name ?? '';
				$this->introduction = $data->introduction ?? '';
				$this->footer = $data->footer ?? '';
				$this->image = $data->image ?? 'none';

				// Set colour to default if empty or missing
				if ( !isset( $data->colour ) || $data->colour == '' ) {
					$this->themeColour = 'lightgrey';
				} else {
					$this->themeColour = $data->colour;
				}

				if ( isset( $data->content ) && is_array( $data->content ) ) {
					$this->content = [];
					foreach ( $data->content as $itemObject ) {
						if ( !is_object( $itemObject ) ) { // Malformed item
							$this->content = null;
							break;
						}
						$item = [];
						$item['title'] = $itemObject->title ?? null;
						$item['image'] = $itemObject->image ?? null;
						$item['displayTitle'] = $itemObject->display_title ?? null;

						$this->content[] = $item;
					}
				}
			}
		}
		$this->decoded = true;
	}

	/**
	 * Resolves the redirect of a Title if it is in fact a redirect.
	 *
	 * Consistent with general MediaWiki behavior, this function does
	 * not resolve double redirects.
	 *
	 * @param Title $title Title which may or may not be a redirect
	 * @return Title
	 */
	public function redirectProof( Title $title ) {
		if ( $title->isRedirect() ) {
			$articleID = $title->getArticleID();
			$wikipage = WikiPage::newFromID( $articleID );
			return $wikipage->getRedirectTarget();
		}
		return $title;
	}

	/**
	 * @return string
	 */
	public function getIntroduction() {
		$this->decode();
		return $this->introduction;
	}

	/**
	 * @return string
	 */
	public function getFooter() {
		$this->decode();
		return $this->footer;
	}

	/**
	 * @return string
	 */
	public function getImage() {
		$this->decode();
		return $this->image;
	}

	/**
	 * @return array
	 */
	public function getContent() {
		$this->decode();
		return $this->content;
	}

	/**
	 * @return string
	 */
	public function getDisplayName() {
		$this->decode();
		return $this->displayName;
	}

	/**
	 * @return string
	 */
	public function getThemeColour() {
		$this->decode();
		return $this->themeColour;
	}

	/**
	 * Fill $output with information derived from the content.
	 *
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 */
	protected function fillParserOutput( Title $title, $revId,
		ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		$parser = MediaWikiServices::getInstance()->getParser();
		$this->decode();

		OutputPage::setupOOUI();

		// Dummy parse intro and footer to get categories and page info for the actual
		// content of *this* page, essentially setting up our real ParserOutput
		$output = $parser->parse(
			$this->getIntroduction() . $this->getFooter(),
			$title,
			$options,
			true,
			true,
			$revId
		);

		// Change $options a bit for the rest of this
		// We may or may not want limit reporting for every piece; we can put this back on
		// later if it turns out we actually do (and only disable it for the header/footer,
		// where it should already be included per the above, I think?)
		$options->enableLimitReport( false );

		$html = '';

		// If error, then bypass all this and just show the offending JSON

		if ( $this->displaymode == 'error' ) {
			$html = '<div class=errorbox>'
			. wfMessage( 'collaborationkit-hub-invalid' )
			. "</div>\n<pre>"
			. $this->errortext
			. '</pre>';
			$output->setText( $html );
		} else {
			// set up hub with theme stuff
			$html .= Html::openElement(
				'div',
				[ 'class' => $this->getHubClasses() ]
			);
			// get page image
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-image' ],
				$this->getParsedImage( $this->getImage(), 200 )
			);
			// get members list
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-members' ],
				$this->getMembersBlock( $title, $options, $output )
			);
			// get parsed intro
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-intro' ],
				$this->getParsedIntroduction( $title, $options )
			);
			// get announcements
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-announcements' ],
				$this->getParsedAnnouncements( $title, $options )
			);
			// get table of contents
			if ( count( $this->getContent() ) > 0 ) {
				$html .= Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-hub-toc' ],
					$this->getTableOfContents( $title, $options )
				);
			}

			$html .= Html::element( 'div', [ 'style' => 'clear:both' ] );

			// get transcluded content
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-content' ],
				$this->getParsedContent( $title, $options, $output )
			);

			$html .= Html::element( 'div', [ 'style' => 'clear:both' ] );

			// get main footer: bottom text under the content
			$footerText = $this->getParsedFooter( $title, $options );
			// only show if it's likely to contain anything visible
			if ( strlen( trim( $footerText ) ) > 0 ) {
				$html .= Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-hub-footer' ],
					$footerText
				);
			}

			if ( !$options->getIsPreview() ) {
				$html .= Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-hub-footer-actions' ],
					$this->getSecondFooter( $title )
				);
			}

			$html .= Html::closeElement( 'div' );

			$output->setText( $html );

			// Add some style stuff
			$output->addModuleStyles( [
				'ext.CollaborationKit.hub.styles',
				'oojs-ui.styles.icons-editing-core',
				'ext.CollaborationKit.icons',
				'ext.CollaborationKit.blots',
				'ext.CollaborationKit.list.styles'
			] );
			$output->addModules( [
				'ext.CollaborationKit.list.members'
			] );
			$output->setEnableOOUI( true );
		}
	}

	/**
	 * Helper function for fillParserOutput to get all the css classes for the
	 * page content
	 *
	 * @return array
	 */
	protected function getHubClasses() {
		$colour = $this->getThemeColour();

		$classes = [
			'mw-ck-collaborationhub',
			'mw-ck-list-square'
		];
		if ( $colour == 'black' ) {
			$classes = array_merge( $classes, [ 'mw-ck-theme' ] );
		} else {
			$classes = array_merge( $classes, [ 'mw-ck-theme-' . $colour ] );
		}

		return $classes;
	}

	/**
	 * Helper function for fillParserOutput
	 *
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param ParserOutput $output
	 * @param CollaborationListContent|null $membersContent Member list Content
	 *  for testing purposes
	 * @return string
	 */
	protected function getMembersBlock( Title $title, ParserOptions $options,
		ParserOutput $output, $membersContent = null
	) {
		$services = MediaWikiServices::getInstance();
		$parser = $services->getParser();
		$user = RequestContext::getMain()->getUser();
		$permissionManager = $services->getPermissionManager();

		$html = '';

		$lang = $options->getTargetLanguage();
		if ( !$lang ) {
			$lang = $title->getPageLanguage();
		}

		$membersPageName = $title->getFullText()
			. '/'
			. wfMessage( 'collaborationkit-hub-pagetitle-members' )
				->inContentLanguage()
				->text();
		$membersTitle = Title::newFromText( $membersPageName );
		$membersTitle = $this->redirectProof( $membersTitle );
		if ( ( $membersTitle->exists()
			&& $membersTitle->getContentModel() == 'CollaborationListContent' )
			|| $membersContent !== null
		) {
			$membersPageID = $membersTitle->getArticleID();
			$output->addJsConfigVars(
				'wgCollaborationKitAssociatedMemberList',
				$membersPageID
			);

			// rawElement is used because we don't want [edit] links or usual
			// header behavior
			$html .= Html::rawElement(
				'h3',
				[],
				wfMessage( 'collaborationkit-hub-members-header' )
			);

			if ( $membersContent === null ) {
				$membersRevision = Revision::newFromTitle(
					$membersTitle,
					0,
					Revision::READ_LATEST
				);
				if ( $membersRevision ) {
					$membersContent = $membersRevision->getContent();
				}
			}
			if ( $membersContent && $membersContent instanceof CollaborationListContent ) {
				$activeCol = wfMessage( 'collaborationkit-column-active' )
					->inContentLanguage()
					->plain();
				$wikitext = $membersContent->convertToWikitext(
					$lang,
					[
						'includeDesc' => false,
						'maxItems' => 3,
						'defaultSort' => 'random',
						'columns' => [ $activeCol ],
						'showColumnHeaders' => false,
						'iconWidth' => 32
					]
				);
			} else {
				// Some sort of error occurred. Probably
				// a race condition.
				// No i18n for this error message, since
				// it should never happen.
				$wikitext = '<span class="error">Cannot include member list</span>';
			}

			$titleParse = $parser->parse( $wikitext, $membersTitle, $options );
			$html .= $this->getTrimmedText( $titleParse );

			$membersViewButton = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-members-view' )
					->inContentLanguage()
					->text(),
				'href' => $membersTitle->getLinkURL()
			] );

			if ( $permissionManager->userCan( 'edit', $user, $membersTitle ) ) {
				$membersJoinButton = new OOUI\ButtonWidget( [
					'label' => wfMessage( 'collaborationkit-hub-members-signup' )
						->inContentLanguage()
						->text(),
					// Going through editor is non-JS fallback
					'href' => $membersTitle->getEditURL(),
					'flags' => [ 'primary', 'progressive' ],
					'classes' => [ 'mw-ck-members-join' ]
				] );

				$membersJoinButton = $membersJoinButton->toString();
			} else {
				$membersJoinButton = '';
			}

			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-members-buttons' ],
				$membersViewButton->toString() . $membersJoinButton
			);
		}

		return $html;
	}

	/**
	 * Helper function for fillParserOutput
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getParsedIntroduction( Title $title, ParserOptions $options ) {
		$parser = MediaWikiServices::getInstance()->getParser();
		$tempOutput = $parser->parse( $this->getIntroduction(), $title, $options );

		return $this->getTrimmedText( $tempOutput );
	}

	/**
	 * Helper function for fillParserOutput
	 *
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param string|null $announcementsText Force-fed announcements HTML for testing purposes
	 * @return string
	 */
	protected function getParsedAnnouncements( Title $title, ParserOptions $options,
		$announcementsText = null
	) {
		$services = MediaWikiServices::getInstance();
		$permissionManager = $services->getPermissionManager();
		$user = RequestContext::getMain()->getUser();

		$announcementsSubpageName = wfMessage( 'collaborationkit-hub-pagetitle-announcements' )
			->inContentLanguage()
			->text();
		$announcementsTitle = Title::newFromText(
			$title->getFullText()
			. '/'
			. $announcementsSubpageName
		);
		$announcementsTitle = $this->redirectProof( $announcementsTitle );

		if ( $announcementsTitle->exists() || $announcementsText !== null ) {
			if ( $announcementsText === null ) {
				$announcementsWikiPage = WikiPage::factory( $announcementsTitle );
				$announcementsOutput = $announcementsWikiPage
					->getContent()
					->getParserOutput( $announcementsTitle );
				$announcementsText = $this->getTrimmedText( $announcementsOutput );
			}

			if ( $permissionManager->userCan( 'edit', $user, $announcementsTitle ) ) {
				$announcementsEditButton = $this->makeEditSectionLink(
					$announcementsTitle->getEditURL(),
					wfMessage( 'edit' )->inContentLanguage()->text(),
					'edit'
				);
			} else {
				$announcementsEditButton = '';
			}

			$announcementsHeader = Html::rawElement(
				'h3',
				[],
				$announcementsSubpageName . $announcementsEditButton
			);

			return $announcementsHeader . $announcementsText;
		}
	}

	/**
	 * Helper function for fillParserOutput
	 *
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getParsedFooter( Title $title, ParserOptions $options ) {
		$parser = MediaWikiServices::getInstance()->getParser();
		$tempOutput = $parser->parse( $this->getFooter(), $title, $options );

		return $this->getTrimmedText( $tempOutput );
	}

	/**
	 * Get some extra buttons for another footer
	 *
	 * @param Title $title
	 * @return string
	 */
	protected function getSecondFooter( Title $title ) {
		$user = RequestContext::getMain()->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$html = '';

		if ( $permissionManager->userCan( 'edit', $user, $title ) ) {
			$html .= new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-manage' )
					->inContentLanguage()
					->text(),
				'href' => $title->getLocalURL( [ 'action' => 'edit' ] ),
				'flags' => [ 'progressive' ],
				'icon' => 'edit'
			] );

			// TODO make sure they have create permission, too
			$html .= new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-addpage' )
					->inContentLanguage()
					->text(),
				'href' => SpecialPage::getTitleFor( 'CreateHubFeature' )
					->getFullURL( [ 'collaborationhub' => $title->getFullText() ] ),
				'flags' => [ 'progressive' ],
				'icon' => 'add'
			] );
		}

		return $html;
	}

	/**
	 * Helper function for fillParserOutput; the main body of the page
	 *
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param ParserOutput $output
	 * @return string
	 */
	protected function getParsedContent( Title $title, ParserOptions $options,
		ParserOutput $output
	) {
		$services = MediaWikiServices::getInstance();
		$parser = $services->getParser();
		$user = RequestContext::getMain()->getUser();
		$permissionManager = $services->getPermissionManager();

		$lang = $options->getTargetLanguage();
		if ( !$lang ) {
			$lang = $title->getPageLanguage();
		}

		$html = '';

		foreach ( $this->getContent() as $item ) {
			if ( !isset( $item['title'] ) || $item['title'] == '' ) {
				continue;
			}
			$spTitle = $this->redirectProof( Title::newFromText( $item['title'] ) );
			$spRev = Revision::newFromTitle( $spTitle );

			// open element and do header
			$html .= $this->makeHeader( $title, $item );

			if ( isset( $spRev ) ) {
				// DO CONTENT FROM PAGE
				/** @var CollaborationHubContent $spContent */
				$spContent = $spRev->getContent();
				$spContentModel = $spRev->getContentModel();

				if ( $spContentModel == 'CollaborationHubContent' ) {
					// this is dumb, but we'll just rebuild the intro here for now
					$text = Html::rawElement(
						'div',
						[ 'class' => 'mw-ck-hub-image' ],
						$spContent->getParsedImage( $spContent->getImage(), 100 )
					);
					$text .= $spContent->getParsedIntroduction( $spTitle, $options );
				} elseif ( $spContentModel == 'CollaborationListContent' ) {
					// convert to wikitext with maxItems limit in place
					/** @var CollaborationListContent $spContent */
					$wikitext = $spContent->convertToWikitext(
						$lang,
						[
							'includeDesc' => false,
							'maxItems' => 4,
							// TODO use a sort according to options in the item line
							'defaultSort' => 'random'
						]
					);
					$text = $parser->parse( $wikitext, $title, $options );
					$text = $this->getTrimmedText( $text );
				} elseif ( $spContentModel == 'wikitext' ) {
					// to grab first section only
					$spContent = $spContent->getSection( 0 );

					// Do template preproccessing magic
					// ... parse, get text into $text
					$rawText = $spContent->serialize();
					// Get rid of all <noinclude>'s.
					$parser->startExternalParse( $title, $options, Parser::OT_WIKI );
					$frame = $parser->getPreprocessor()->newFrame()->newChild( [], $spTitle );
					$node = $parser->preprocessToDom( $rawText, Parser::PTD_FOR_INCLUSION );
					$processedText = $frame->expand(
						$node,
						PPFrame::RECOVER_ORIG & ( ~PPFrame::NO_IGNORE )
					);
					$parsedWikitext = $parser->parse( $processedText, $title, $options );
					$text = $this->getTrimmedText( $parsedWikitext );
					$output->addModuleStyles( $parsedWikitext->getModuleStyles() );
				} else {
					// Parse whatever (else) as whatever
					$contentOutput = $spContent->getParserOutput( $spTitle, $spRev->getId(), $options );
					$output->addModuleStyles( $contentOutput->getModuleStyles() );
					$text = $contentOutput->getRawText();
				}

				$html .= Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-hub-section-main' ],
					$text
				);

				// register as template for stuff
				$output->addTemplate(
					$spTitle,
					$spTitle->getArticleID(),
					$spRev->getId()
				);
			} else {
				// DO CONTENT FOR NOT YET MADE PAGE
				if ( $permissionManager->userCan( 'create', $user, $spTitle ) ) {
					$html .= Html::rawElement(
						'p',
						[ 'class' => 'mw-ck-hub-missingfeature-note' ],
						wfMessage( 'collaborationkit-hub-missingpage-note' )
							->inContentLanguage()
							->parse()
					);

					$html .= new OOUI\ButtonWidget( [
						'label' => wfMessage( 'collaborationkit-hub-missingpage-create' )
							->inContentLanguage()
							->text(),
						'href' => SpecialPage::getTitleFor( 'CreateHubFeature' )
							->getFullURL( [
								'collaborationhub' => $title->getFullText(),
								'feature' => $spTitle->getSubpageText()
							] ),
						'flags' => [ 'progressive' ]
					] );
				} else {
					// Different message; they can't actually create it
					$html .= Html::rawElement(
						'p',
						[ 'class' => 'mw-ck-hub-missingfeature-note' ],
						wfMessage( 'collaborationkit-hub-missingpage-protected-note' )
							->inContentLanguage()
							->parse()
					);
				}

				$html .= new OOUI\ButtonWidget( [
					'label' => wfMessage( 'collaborationkit-hub-missingpage-purgecache' )
						->inContentLanguage()
						->text(),
					'href' => $title->getFullURL( [ 'action' => 'purge' ] )
				] );

				// register as template for stuff
				$output->addTemplate( $spTitle, $spTitle->getArticleID(), null );
			}

			$html .= Html::closeElement( 'div' );
		}

		return $html;
	}

	/**
	 * Helper function for getParsedContent for making subpage section headers
	 *
	 * @param Title $title
	 * @param array $contentItem Data for the content item we're generating the
	 *  header for
	 * @return string html (NOTE THIS IS AN OPEN DIV)
	 */
	protected function makeHeader( Title $title, array $contentItem ) {
		static $tocLinks = []; // All used ids for the sections for the toc

		$spTitle = Title::newFromText( $contentItem['title'] );
		$spTitle = $this->redirectProof( $spTitle );
		$spRev = Revision::newFromTitle( $spTitle );
		$user = RequestContext::getMain()->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		// Get display name
		if ( isset( $contentItem['displayTitle'] ) ) {
			$spPage = $contentItem['displayTitle'];
		} else {
			$spPage = $spTitle->getSubpageText();
		}

		// Get icon
		$image = $contentItem['image'] ?? null;
		$imageHtml = CollaborationKitImage::makeImage(
			$image,
			35,
			[
				'link' => $spTitle->getText(),
				'fallback' => $spPage,
				'classes' => [ 'mw-ck-section-image' ]
			]
		);

		// Generate an id for the section for anchors
		// Make sure this matches the ToC anchor generation
		$spPageLink = Sanitizer::escapeId( htmlspecialchars( $spPage ) );
		$spPageLink2 = $spPageLink;
		$spPageLinkCounter = 1;
		while ( in_array( $spPageLink2, $tocLinks ) ) {
			$spPageLink2 = $spPageLink . $spPageLinkCounter;
			$spPageLinkCounter++;
		}
		$tocLinks[] = $spPageLink2;

		// Get editsection-style links for the subpage
		$sectionLinks = [];
		$sectionLinksText = '';
		if ( isset( $spRev ) ) {
			$sectionLinks['viewLink'] = [];
			$sectionLinks['viewLink']['title'] = $spTitle->getLinkURL();
			$sectionLinks['viewLink']['msg'] = wfMessage( 'collaborationkit-hub-subpage-view' )
				->inContentLanguage()
				->text();
			$sectionLinks['viewLink']['icon'] = 'search';

			if ( $permissionManager->userCan( 'edit', $user, $spTitle ) ) {
				$sectionLinks['editLink'] = [];
				$sectionLinks['editLink']['title'] = $spTitle->getEditURL();
				$sectionLinks['editLink']['msg'] = wfMessage( 'edit' )
					->inContentLanguage()
					->text();
				$sectionLinks['editLink']['icon'] = 'edit';
			}
		}
		foreach ( $sectionLinks as $sectionLink ) {
			$sectionLinksText .= $this->makeEditSectionLink(
				$sectionLink['title'],
				$sectionLink['msg'],
				$sectionLink['icon']
			);
		}

		$sectionButtons = '';
		if ( $sectionLinksText !== '' ) {
			$sectionButtons = Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-section-buttons' ],
				$sectionLinksText
			);
		}

		// Assemble header
		// Open general section here since we have the id here
		$html = Html::openElement(
			'div',
			[
				'class' => 'mw-ck-hub-section',
				'id' => $spPageLink2
			]
		);
		$html .= Html::rawElement(
			'div',
			[
				'class' => 'mw-ck-hub-section-header'
			],
			Html::rawElement(
				'h2',
				[],
				$imageHtml .
				Html::element(
					'span',
					[ 'class' => 'mw-headline' ],
					$spPage
				) . $sectionButtons
			)
		);

		OutputPage::setupOOUI();
		return $html;
	}

	/**
	 * Helper function for fillParserOutput for making editsection links in
	 *  headers
	 *
	 * @param string $link Target URL
	 * @param string $message Message to display
	 * @param string $icon Icon to display alongside the message, based on
	 *  OOUI definitions
	 * @return OOUI\ButtonWidget
	 */
	protected function makeEditSectionLink( $link, $message, $icon ) {
		return new OOUI\ButtonWidget( [
			'classes' => [ 'mw-ck-hub-section-button mw-editsection-like' ],
			'label' => $message,
			'href' => $link,
			'framed' => false,
			'icon' => $icon,
			'flags' => [ 'progressive' ]
		] );
	}

	/**
	 * Helper function for fillParserOutput: the table of contents
	 *
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getTableOfContents( Title $title, ParserOptions $options ) {
		$toc = new CollaborationHubTOC();
		return $toc->renderToC( $this->content );
	}

	/**
	 * Generate an image based on what's in 'image', be it an icon or a file
	 *
	 * @param string $image Filename or icon name
	 * @param int $size int for non-icon images
	 * @return string HTML
	 */
	public function getParsedImage( $image, $size = 200 ) {
		return CollaborationKitImage::makeImage(
			$image,
			$size,
			[ 'fallback' => 'puzzlepiece' ]
		);
	}

	/**
	 * Find the parent hub, if any.
	 *
	 * Returns the first CollaborationHub Title found, even if more are higher
	 * up, or null if none
	 *
	 * @param Title $title Title to start looking from
	 * @return Title|null Title of parent hub or null if none was found
	 */
	public static function getParentHub( Title $title ) {
		global $wgCollaborationHubAllowedNamespaces;

		$namespace = $title->getNamespace();
		if ( MWNamespace::hasSubpages( $namespace ) &&
			isset( $wgCollaborationHubAllowedNamespaces[$namespace] ) &&
			$wgCollaborationHubAllowedNamespaces[$namespace]
		) {
			$parentTitle = $title->getBaseTitle();
			while ( !$title->equals( $parentTitle ) ) {
				$parentRev = Revision::newFromTitle( $parentTitle );
				if ( $parentTitle->getContentModel() == 'CollaborationHubContent'
					&& isset( $parentRev )
				) {
					return $parentTitle;
				}

				// keep looking
				$title = $parentTitle;
			}
		}

		// Nothing was found
		return null;
	}

	/**
	 * Converts content between wikitext and JSON.
	 *
	 * @param string $toModel
	 * @param string $lossy Flag, set to "lossy" to allow lossy conversion.
	 *  If lossy conversion is not allowed, full round-trip conversion is
	 *  expected to work without losing information.
	 * @return Content
	 */
	public function convert( $toModel, $lossy = '' ) {
		if ( $toModel === CONTENT_MODEL_WIKITEXT && $lossy === 'lossy' ) {
			// Not ideal at all, but without access to the name of the page
			// being transcluded, we can't embed the rest of the page. This is
			// just a holdover to prevent the thing from throwing an exception.
			$this->decode();
			$image = $this->getImage();
			$intro = $this->getIntroduction();
			$text = "<div style='margin:0 2em 2em 0;'>[[File:$image|200px|left]]</div>
				\n<div style='font-size:115%;'>$intro</div>";
			return ContentHandler::makeContent( $text, null, $toModel );
		} elseif ( $toModel === CONTENT_MODEL_JSON ) {
			return ContentHandler::makeContent(
				$this->getText(),
				null,
				$toModel
			);
		}
		return parent::convert( $toModel, $lossy );
	}

	/**
	 * Convert JSON to markup that's easier for humans.
	 *
	 * @return string
	 */
	public function convertToHumanEditable() {
		$this->decode();
		return CollaborationKitSerialization::getSerialization( [
			$this->displayName,
			$this->introduction,
			$this->footer,
			$this->image,
			$this->themeColour,
			$this->getHumanEditableContent()
		] );
	}

	/**
	 * Get the list of items in human editable form.
	 *
	 * @return string
	 * @todo Should this be i18n-ized?
	 */
	public function getHumanEditableContent() {
		$this->decode();

		$out = '';
		foreach ( $this->content as $item ) {
			$out .= self::escapeForHumanEditable( $item['title'] );
			if ( isset( $item['image'] ) ) {
				$out .= '|image='
					. self::escapeForHumanEditable( $item['image'] );
			}
			if ( isset( $item['displayTitle'] ) ) {
				$out .= '|display_title='
					. self::escapeForHumanEditable( $item['displayTitle'] );
			}
			if ( substr( $out, -1 ) === '|' ) {
				$out = substr( $out, 0, strlen( $out ) - 1 );
			}
			$out .= "\n";
		}
		return $out;
	}

	/**
	 * Escape characters used as separators in human editable mode.
	 *
	 * @param string $text
	 * @return string Escaped text
	 * @throws MWContentSerializationException
	 * @todo Unclear if this is best approach. Alternative might be
	 *  to use &#xA; Or an obscure unicode character like ␊ (U+240A).
	 */
	public static function escapeForHumanEditable( $text ) {
		if ( strpos( $text, '{{!}}' ) !== false ) {
			// Maybe we should use \| too, but that's not MW like.
			throw new MWContentSerializationException( "{{!}} in content" );
		}
		if ( strpos( $text, "\\\n" ) !== false ) {
			// @todo We don't currently handle this properly.
			throw new MWContentSerializationException( "Line ending with a \\" );
		}
		$text = strtr( $text, [
			"\n" => '\n',
			'\n' => '\\\\n',
			'|' => '{{!}}'
		] );
		return $text;
	}

	/**
	 * Removes escape characters inserted in human editable mode.
	 *
	 * @param string $text
	 * @return string Unescaped text
	 */
	public static function unescapeForHumanEditable( $text ) {
		$text = strtr( $text, [
			'\\\\n' => "\\n",
			'\n' => "\n",
			'{{!}}' => '|'
		] );
		return $text;
	}

	/**
	 * Convert from human editable form into a (php) array
	 *
	 * @param string $text Text to convert
	 * @return array Result of converting it to native form
	 */
	public static function convertFromHumanEditable( $text ) {
		$res = [];
		$split = explode( CollaborationKitSerialization::SERIALIZATION_SPLIT, $text );

		$res['display_name'] = $split[0];
		$res['introduction'] = $split[1];
		$res['footer'] = $split[2];
		$res['image'] = $split[3];
		$res['colour'] = $split[4];
		$content = $split[5];
		if ( trim( $content ) == '' ) {
			$res['content'] = [];
		} else {
			$listLines = explode( "\n", $content );
			foreach ( $listLines as $line ) {
				$res['content'][] = self::convertFromHumanEditableItemLine( $line );
			}
		}
		return $res;
	}

	/**
	 * Helper function that converts individual lines from convertFromHumanEditable.
	 *
	 * @param string $line
	 * @return array
	 * @throws MWContentSerializationException
	 */
	private static function convertFromHumanEditableItemLine( $line ) {
		$parts = explode( '|', $line );
		$parts = array_map( [ __CLASS__, 'unescapeForHumanEditable' ], $parts );
		$itemRes = [ 'title' => $parts[0] ];
		if ( count( $parts ) > 1 ) {
			$parts = array_slice( $parts, 1 );
			foreach ( $parts as $part ) {
				list( $key, $value ) = explode( '=', $part );
				switch ( $key ) {
					case 'image':
					case 'display_title':
						$itemRes[$key] = $value;
						break;
					default:
						throw new MWContentSerializationException(
							'Unrecognized option for list item:' .
							wfEscapeWikiText( $key )
						);
				}
			}
		}
		return $itemRes;
	}

	/**
	 * Hook to use custom edit page for lists
	 *
	 * @param WikiPage|Article|ImagePage|CategoryPage|Page $page
	 * @param User $user
	 * @return bool
	 */
	public static function onCustomEditor( Page $page, User $user ) {
		if ( $page->getContentModel() === __CLASS__ ) {
			$editor = new CollaborationHubContentEditor( $page );
			$editor->setContextTitle( $page->getTitle() );
			$editor->edit();
			return false;
		}
	}

	/**
	 * Helper function to return only the specific text from a ParserOutput object
	 * so we don't fill the page with unnecessary wrappers and stuff
	 *
	 * @param ParserOutput $tempOutput
	 * @return string
	 */
	private function getTrimmedText( $tempOutput ) {
		return $tempOutput->getText( [ 'unwrap' => true ] );
	}
}
