<?php

/**
 * Structured hub pages!
 *
 * Json structure is defined in CollaborationHubContentSchema.php.
 *
 */
class CollaborationHubContent extends JsonContent {

	const HUMAN_DESC_SPLIT = "\n-----------------------\n";

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

	/**
	 * 23 preset colours; actual colour values are set in the extension.json and less modules
	 * @return array
	 */
	public static function getThemeColours() {
		return [
			'red1',
			'red2',
			'grey1',
			'grey2',
			'blue1',
			'blue2',
			'blue3',
			'blue4',
			'blue5',
			'blue6',
			'purple1',
			'purple2',
			'purple3',
			'purple4',
			'purple5',
			'yellow1',
			'yellow2',
			'yellow3',
			'yellow4',
			'green1',
			'green2',
			'green3',
			'black'
		];
	}

	function __construct( $text ) {
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
			// TODO: The schema should be checking for required fields but for some reason that doesn't work
			if ( !isset( $jsonParse->value->content ) ) {
				return false;
			}
			// Forcing the object to become an array
			$jsonAsArray = json_decode( json_encode( $jsonParse->getValue() ), true );
			try {
				EventLogging::schemaValidate( $jsonAsArray, $hubSchema );
				return true;
			} catch ( JsonSchemaException $e ) {
				return false;
			}
			return false;
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
					$this->errortext = htmlspecialchars( $this->getNativeData() );
				} else {
					$this->errortext = FormatJson::encode( $data, true, FormatJson::ALL_OK );
				}
			} else {
				$this->displayName = isset( $data->display_name ) ? $data->display_name : '';
				$this->introduction = isset( $data->introduction ) ? $data->introduction : '';
				$this->footer = isset( $data->footer ) ? $data->footer : '';
				$this->image = isset( $data->image ) ? $data->image : 'none';

				// Set colour to default if empty or missing
				if ( !isset( $data->colour ) || $data->colour == '' ) {
					$this->themeColour = 'blue5';
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
						$item['title'] = isset( $itemObject->title ) ? $itemObject->title : null;
						$item['image'] = isset( $itemObject->image ) ? $itemObject->image : null;
						$item['displayTitle'] = isset( $itemObject->display_title ) ? $itemObject->display_title : null;

						$this->content[] = $item;
					}
				}
			}
		}
		$this->decoded = true;
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
	 * @param $title Title
	 * @param $revId int
	 * @param $options ParserOptions
	 * @param $generateHtml bool
	 * @param $output ParserOutput
	 */
	protected function fillParserOutput( Title $title, $revId, ParserOptions $options,
		$generateHtml, ParserOutput &$output
	) {
		global $wgParser;
		$this->decode();

		// Dummy parse intro and footer to get categories and whatnot
		$output = $wgParser->parse( $this->getIntroduction() . $this->getFooter(), $title, $options, true, true, $revId );
		$html = '';

		// If error, then bypass all this and just show the offending JSON

		if ( $this->displaymode == 'error' ) {
			$html = '<div class=errorbox>' . wfMessage( 'collaborationkit-hub-invalid' ) . "</div>\n<pre>" . $this->errortext . '</pre>';
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
				$this->getMembersBlock( $title, $options )
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
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-toc' ],
				$this->getTableOfContents( $title, $options )
			);
			// get transcluded content
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-content' ],
				$this->getParsedContent( $title, $options, $output )
			);
			// get footer
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-footer' ],
				$this->getParsedFooter( $title, $options )
			);
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-footer-actions' ],
				$this->getSecondFooter( $title )
			);
			$html .= Html::closeElement( 'div' );

			$output->setText( $html );

			// Add some style stuff
			$output->addModuleStyles( 'ext.CollaborationKit.hub.styles' );
			$output->addModules( 'ext.CollaborationKit.icons' );
			$output->addModules( 'ext.CollaborationKit.blots' );
			$output->addModules( 'ext.CollaborationKit.list.styles' );
			$output->setEnableOOUI( true );
		}
	}

	/**
	 * Helper function for fillParserOutput to get all the css classes for the page content
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
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	protected function getMembersBlock( Title $title, ParserOptions $options ) {
		global $wgParser;

		$html = '';

		$lang = $options->getTargetLanguage();
		if ( !$lang ) {
			$lang = $title->getPageLanguage();
		}

		$membersPageName = $title->getFullText() . '/' . wfMessage( 'collaborationkit-hub-pagetitle-members' )->inContentLanguage()->text();
		$membersTitle = Title::newFromText( $membersPageName );
		if ( $membersTitle->exists() ) {
			// rawElement is used because we don't want [edit] links or usual header behavior
			$html .= Html::rawElement(
				'h3',
				[],
				wfMessage( 'collaborationkit-hub-members-header' )
			);

			$membersContent = Revision::newFromTitle( $membersTitle )->getContent();
			$wikitext = $membersContent->convertToWikitext(
				$lang,
				[
					'includeDesc' => false,
					'maxItems' => 3,
					'defaultSort' => 'random'
				]
			);

			$html .= $wgParser->parse( $wikitext, $membersTitle, $options )->getText();

			$membersViewButton = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-members-view' )->inContentLanguage()->text(),
				'href' => $membersTitle->getLinkURL()
			] );
			$membersJoinButton = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-members-signup' )->inContentLanguage()->text(),
				'href' => $membersTitle->getEditURL(), // Going through editor is non-JS fallback
				'flags' => [ 'primary', 'progressive' ]
			] );

			OutputPage::setupOOUI();
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-members-buttons' ],
				$membersViewButton->toString() . $membersJoinButton->toString()
			);
		}
		return $html;
	}

	/**
	 * Helper function for fillParserOutput
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	protected function getParsedIntroduction( Title $title, ParserOptions $options ) {
		global $wgParser;
		$tempOutput = $wgParser->parse( $this->getIntroduction(), $title, $options );

		return $tempOutput->getText();
	}

	/**
	 * Helper function for fillParserOutput
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	protected function getParsedAnnouncements( Title $title, ParserOptions $options ) {
		$announcementsSubpageName = wfMessage( 'collaborationkit-hub-pagetitle-announcements' )->inContentLanguage()->text();
		$announcementsTitle = Title::newFromText( $title->getFullText() . '/' . $announcementsSubpageName );

		if ( $announcementsTitle->exists() ) {
			$announcementsWikiPage = WikiPage::factory( $announcementsTitle );
			$announcementsText = $announcementsWikiPage->getContent()->getParserOutput( $announcementsTitle )->getText();

			$announcementsEditLink = Html::rawElement(
				'a',
				[ 'href' => $announcementsTitle->getEditURL() ],
				wfMessage( 'edit' )
			);

			$announcementsHeader = Html::rawElement(
				'h3',
				[],
				$announcementsSubpageName . $this->makeEditSectionLink( $announcementsEditLink )
			);
			return $announcementsHeader . $announcementsText;
		}
	}

	/**
	 * Helper function for fillParserOutput
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	protected function getParsedFooter( Title $title, ParserOptions $options ) {
		global $wgParser;
		$tempOutput = $wgParser->parse( $this->getFooter(), $title, $options );

		return $tempOutput->getText();
	}

	/**
	 * Get some extra buttons for another footer
	 * @param $title Title
	 * @return string
	 */
	protected function getSecondFooter( Title $title ) {
		$html = '';

		if ( $title->userCan( 'edit' ) ) {
			$html .= new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-manage' )->inContentLanguage()->text(),
				'href' => $title->getLocalURL( [ 'action' => 'edit' ] ),
				'flags' => [ 'primary', 'progressive' ]
			] );

			// TODO make sure they have create permission, too
			$html .= new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-addpage' )->inContentLanguage()->text(),
				'href' => SpecialPage::getTitleFor( 'CreateHubFeature' )->getFullUrl( [ 'collaborationhub' => $title->getFullText() ] ),
				'flags' => [ 'primary', 'progressive' ]
			] );
		}

		return $html;
	}

	/**
	 * Helper function for fillParserOutput; the bulk of the actual content
	 * @param $title Title
	 * @param $options ParserOptions
	 * @param &$output ParserOutput
	 * @return string
	 */
	protected function getParsedContent( Title $title, ParserOptions $options, ParserOutput &$output ) {
		global $wgParser;

		$lang = $options->getTargetLanguage();
		if ( !$lang ) {
			$lang = $title->getPageLanguage();
		}

		$html = '';

		foreach ( $this->getContent() as $item ) {
			if ( !isset( $item['title'] ) || $item['title'] == '' ) {
				continue;
			}
			$spTitle = Title::newFromText( $item['title'] );
			$spRev = Revision::newFromTitle( $spTitle );

			// open element and do header
			$html .= $this->makeHeader( $title, $item );

			if ( isset( $spRev ) ) {
				// DO CONTENT FROM PAGE
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
					$wikitext = $spContent->convertToWikitext(
						$lang,
						[
							'includeDesc' => false,
							'maxItems' => 4,
							// TODO use a sort according to options in the item line
							'defaultSort' => 'random'
						]
					);
					$text = $wgParser->parse( $wikitext, $title, $options )->getText();
				} elseif ( $spContentModel == 'wikitext' ) {
					// to grab first section only
					$spContent = $spContent->getSection( 0 );

					// Do template preproccessing magic
					// ... parse, get text into $text
					$rawText = $spContent->serialize();
					// Get rid of all <noinclude>'s.
					$wgParser->startExternalParse( $title, $options, Parser::OT_WIKI );
					$frame = $wgParser->getPreprocessor()->newFrame()->newChild( [], $spTitle );
					$node = $wgParser->preprocessToDom( $rawText, Parser::PTD_FOR_INCLUSION );
					$processedText = $frame->expand( $node, PPFrame::RECOVER_ORIG & ( ~PPFrame::NO_IGNORE ) );
					$parsedWikitext = $wgParser->parse( $processedText, $title, $options );
					$text = $parsedWikitext->getText();
					$output->addModuleStyles( $parsedWikitext->getModuleStyles() );
				} else {
					// Parse whatever (else) as whatever
					$contentOutput = $spContent->getParserOutput( $spTitle, $spRev, $options );
					$output->addModuleStyles( $contentOutput->getModuleStyles() );
					$text = $contentOutput->getRawText();
				}

				$html .= $text;

				// register as template for stuff
				$output->addTemplate( $spTitle, $spTitle->getArticleId(), $spRev->getId() );
			} else {
				// DO CONTENT FOR NOT YET MADE PAGE
				$html .= Html::rawElement(
					'p',
					[ 'class' => 'mw-ck-hub-missingfeature-note' ],
					wfMessage( 'collaborationkit-hub-missingpage-note' )->inContentLanguage()->parse()
				);

				$html .= new OOUI\ButtonWidget( [
					'label' => wfMessage( 'collaborationkit-hub-missingpage-create' )->inContentLanguage()->text(),
					'href' => SpecialPage::getTitleFor( 'CreateHubFeature' )->getFullUrl( [ 'collaborationhub' => $title->getFullText(), 'feature' => $spTitle->getSubpageText() ] )
				] );

				// register as template for stuff
				$output->addTemplate( $spTitle, $spTitle->getArticleId(), null );
			}

			$html .= Html::closeElement( 'div' );
		}

		return $html;
	}

	/**
	 * Helper function for getParsedcontent for making subpage section headers
	 * @param $contentItem array of data for the content item we're generating the header for
	 * @return string html (NOTE THIS IS AN OPEN DIV)
	 */
	protected function makeHeader( Title $title, array $contentItem ) {
		global $wgParser;
		static $tocLinks = []; // All used ids for the sections for the toc
		$linkRenderer = $wgParser->getLinkRenderer();

		$spTitle = Title::newFromText( $contentItem['title'] );
		$spRev = Revision::newFromTitle( $spTitle );

		// Get display name
		if ( isset( $contentItem['displayTitle'] ) ) {
			$spPage = $contentItem['displayTitle'];
		} else {
			$spPage = $spTitle->getSubpageText();
		}

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
			$sectionLinks[ 'viewLink' ] = $linkRenderer->makeLink(
				$spTitle,
				wfMessage( 'collaborationkit-hub-subpage-view' )->inContentLanguage()->text()
			);
		}
		if ( $spTitle->userCan( 'edit' ) ) {
			if ( isset( $spRev ) ) {
				$linkString = 'edit';
				// TODO get appropriate edit link if it's something weird
				$sectionLinks['edit'] = $linkRenderer->makeLink(
					$spTitle,
					wfMessage( $linkString )->inContentLanguage()->text(),
					[],
					[ 'action' => 'edit' ]
				);
			} else {
				$linkString = 'create';
				$sectionLinks['edit'] = $linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CreateHubFeature' ),
					wfMessage( $linkString )->inContentLanguage()->text(),
					[],
					[ 'collaborationhub' => $title->getPrefixedDBKey(), 'feature' => $spTitle->getSubpageText() ]
				);
			}

		}
		if ( $title->userCan( 'edit' ) ) {
			$sectionLinks['removeLink'] = $linkRenderer->makeLink(
				$title,
				wfMessage( 'collaborationkit-hub-subpage-remove' )->inContentLanguage()->text(),
				[],
				[ 'action' => 'edit' ]
			);
		}
		foreach ( $sectionLinks as $sectionLink ) {
			$sectionLinksText .= $this->makeEditSectionLink( $sectionLink );
		}
		$sectionLinksText = Html::rawElement(
			'span',
			[ 'class' => 'mw-editsection' ],
			$sectionLinksText
		);

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
			'h2',
			[],
			Html::element(
				'span',
				[ 'class' => 'mw-headline' ],
				$spPage
			) . $sectionLinksText
		);

		OutputPage::setupOOUI();
		return $html;
	}

	/**
	 * Helper function for fillParserOutput for making editsection links in headers
	 * @param $link string html of the link itself
	 * @return string html
	 */
	protected function makeEditSectionLink( $link ) {
		$html = Html::rawElement(
			'span',
			[ 'class' => 'mw-editsection' ],
			Html::element(
				'span',
				[ 'class' => 'mw-editsection-bracket' ],
				'['
			) .
			Html::rawElement(
				'span',
				[],
				$link
			) .
			Html::element(
				'span',
				[ 'class' => 'mw-editsection-bracket' ],
				']'
			)
		);

		return $html;
	}

	/**
	 * Helper function for fillParserOutput: the table of contents
	 * @param $title Title
	 * @param $options ParserOptions
	 * @return string
	 */
	protected function getTableOfContents( Title $title, ParserOptions $options ) {
		$toc = new CollaborationHubTOC();

		return $toc->renderTOC( $this->content, $this->themeColour );
	}

	/**
	 * Generate an image based on what's in 'image', be it an icon or a file
	 * @param $fallback string for what to do for no icons - nothing, random, specific icon...
	 * @param $size int for non-icon images
	 * @param $seed string fallback seed for explicitly something somethinged ones
	 * @return string
	 */
	public function getParsedImage( $image, $size = 200 ) {
		return CollaborationKitIcon::makeIconOrImage( $this->getImage(), $size, 'puzzlepiece' );
	}

	/**
	 * Find the parent hub, if any.
	 * Returns the first CollaborationHub Title found, even if more are higher up, or null if none
	 * @param $title Title to start looking from
	 * @return Title|null
	 */
	public static function getParentHub( Title $title ) {
		global $wgCollaborationHubAllowedNamespaces;

		$namespace = $title->getNamespace();
		if ( MWNamespace::hasSubpages( $namespace ) &&
			in_array( $namespace, array_keys( array_filter( $wgCollaborationHubAllowedNamespaces ) ) ) ) {

			$parentTitle = $title->getBaseTitle();
			while ( !$title->equals( $parentTitle ) ) {
				$parentRev = Revision::newFromTitle( $parentTitle );
				if ( $parentTitle->getContentModel() == 'CollaborationHubContent' && isset( $parentRev ) ) {
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
	 * @param $toModel string
	 * @param $lossy string
	 */
	public function convert( $toModel, $lossy = '' ) {
		if ( $toModel === CONTENT_MODEL_WIKITEXT && $lossy === 'lossy' ) {
			global $wgContLang;
			// using wgContLang is kind of icky. Maybe we should transclude
			// from mediawiki namespace, or give up on not splitting the
			// parser cache and just use {{int:... (?)
			$renderOpts = $this->getFullRenderListOptions();
			$text = $this->convertToWikitext( $wgContLang, $renderOpts );
			return ContentHandler::makeContent( $text, null, $toModel );
		} elseif ( $toModel === CONTENT_MODEL_JSON ) {
			return ContentHandler::makeContent( $this->getNativeData(), null, $toModel );
		}
		return parent::convert( $toModel, $lossy );
	}

	/**
	 * Convert JSON to markup that's easier for humans.
	 */
	public function convertToHumanEditable() {
		$this->decode();

		$output = $this->displayName;
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->introduction;
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->footer;
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->image;
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->themeColour;
		$output .= self::HUMAN_DESC_SPLIT;
		$output .= $this->getHumanEditableContent();
		return $output;
	}

	/**
	 * Get the list of items in human editable form.
	 *
	 * @todo Should this be i18n-ized?
	 */
	public function getHumanEditableContent() {
		$this->decode();

		$out = '';
		foreach ( $this->content as $item ) {
			$out .= $this->escapeForHumanEditable( $item['title'] );
			if ( isset ( $item['image'] ) ) {
				$out .= "|image=" . $this->escapeForHumanEditable( $item['image'] );
			}
			if ( isset( $item['displayTitle'] ) ) {
				$out .= "|display_title=" . $this->escapeForHumanEditable( $item['displayTitle'] );
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
	 * @todo Unclear if this is best approach. Alternative might be
	 *  to use &#xA; Or an obscure unicode character like ␊ (U+240A).
	 */
	private function escapeForHumanEditable( $text ) {
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
			'\n'=> '\\\\n',
			'|' => '{{!}}'
		] );
		return $text;
	}

	/**
	 * Removes escape characters inserted in human editable mode.
	 *
	 * @param $text string
	 * @return string
	 */
	private static function unescapeForHumanEditable( $text ) {
		$text = strtr( $text, [
			'\\\\n'=> "\\n",
			'\n' => "\n",
			'{{!}}' => '|'
		] );
		return $text;
	}

	/**
	 * Convert from human editable form into a (php) array
	 *
	 * @param $text String text to convert
	 * @return Array Result of converting it to native form
	 */
	public static function convertFromHumanEditable( $text ) {
		$res = [];
		$split = explode( self::HUMAN_DESC_SPLIT, $text );

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
	 * @param $line string
	 * @return array
	 */
	private static function convertFromHumanEditableItemLine( $line ) {
		$parts = explode( "|", $line );
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
					$context = wfEscapeWikiText( substr( $part, 30 ) );
					if ( strlen( $context ) === 30 ) {
						$context .= '...';
					}
					throw new MWContentSerializationException(
						"Unrecognized option for list item:" .
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
	 * @param $page Page
	 * @param $user User
	 */
	public static function onCustomEditor( Page $page, User $user ) {
		if ( $page->getContentModel() === __CLASS__ ) {
			$editor = new CollaborationHubContentEditor( $page );
			$editor->edit();
			return false;
		}
	}
}
