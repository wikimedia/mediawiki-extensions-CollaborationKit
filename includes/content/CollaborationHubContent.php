<?php

/**
 * Structured hub pages!
 *
 * Json structure is as follows:
 * {
 * 	"display_name": "Display name for the page/project",
 *	"image": "A file on-wiki or an id that matches one of the canned icons",
 *	"colour": "One of the 23 preset theme colours",
 *	"introduction": "Some arbitrary wikitext to appear at the top",
 *	"content": [
 *		{
 *			"title": "The title, generally a subpage; we'll force this later",
 *			"image": "Image or icon to use",
 *			"display_title": "What to show on the page (defaults to {{SUBPAGENAME}} otherwise)",
 *			...
 *		},
 *		...
 *	],
 *	"footer": "Some more arbitrary wikitext to appear at the bottom"
 * }
 *
 */
class CollaborationHubContent extends JsonContent {

	/**
	 * @var string
	 */
	protected $displayName;

	/**
	 * @var string
	 */
	protected $image;

	/**
	 * @var string
	 */
	protected $introduction;

	/**
	 * Array of pages included in the hub
	 * @var array
	 */
	protected $content;

	/**
	 * @var string
	 */
	protected $footer;

	/**
	 * @var string
	 */
	protected $themeColour;

	/**
	 * 23 preset colours; actual colour values are set in the extension.json and less modules
	 * @var array
	 */
	protected $themeColours = [
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

	/**
	 * Whether contents have been populated
	 * @var bool
	 */
	protected $decoded = false;

	function __construct( $text ) {
		parent::__construct( $text, 'CollaborationHubContent' );
	}

	/**
	 * Decode and validate the contents
	 * @return bool Whether the contents are valid
	 */
	public function isValid() {
		$this->decode();

		if (
			!is_string( $this->introduction ) ||
			!is_string( $this->footer ) ||
			!is_string( $this->displayName ) ||
			!is_string( $this->image )
		) {
			return false;
		}

		// Check if colour is one of the presets; if somehow this isn't a string and still matches one of the presets, I don't even want to know
		if ( !in_array( $this->themeColour, $this->themeColours ) ) {
			return false;
		}

		// 'content' needs to be an array of pages
		if ( is_array( $this->content ) ) {
			foreach ( $this->content as $contentItem ) {
				// 'title' is required; 'image' is optional
				if (
					!is_string( $contentItem['title'] ) ||
					( !is_string( $contentItem['image'] ) && $contentItem['image'] !== null ) ||
					( !is_string( $contentItem['displayTitle'] ) && $contentItem['displayTitle'] !== null )
				) {
					return false;
				}
			}
		} else {
			return false;
		}

		return true;
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
	 * @return string
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
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput $output
	 */
	protected function fillParserOutput( Title $title, $revId, ParserOptions $options,
		$generateHtml, ParserOutput &$output
	) {
		global $wgParser;
		$this->decode();

		// Dummy parse intro and footer to get categories and whatnot
		$output = $wgParser->parse( $this->getIntroduction() . $this->getFooter(), $title, $options, true, true, $revId );

		$html = '';
		// set up hub with theme stuff
		$html .= Html::openElement(
			'div',
			[ 'class' => $this->getHubClasses() ]
		);
		// get page image
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'wp-header-image' ],
			// TODO move all image stuff to ToC class (what is that class even going to be, anyway?)
			$this->getParsedImage( $this->getImage(), 200 )
		);
		// get members list
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'wp-members' ],
			$this->getMembersBlock( $title, $options )
		);
		// get parsed intro
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'wp-intro' ],
			$this->getParsedIntroduction( $title, $options )
		);
		// get table of contents
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'wp-toc' ],
			$this->getTableOfContents( $title, $options )
		);
		// get transcluded content
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'wp-content' ],
			$this->getParsedContent( $title, $options, $output )
		);
		// get footer
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'wp-footer' ],
			$this->getParsedFooter( $title, $options )
		);
		$html .= Html::closeElement( 'div' );

		$output->setText( $html );

		// Add some style stuff
		$output->addModuleStyles( 'ext.CollaborationKit.main' );
		$output->addModules( 'ext.CollaborationKit.icons' );
		$output->addModules( 'ext.CollaborationKit.blots' );
		$output->setEnableOOUI( true );
	}

	/**
	 * Helper function for fillParserOutput to get all the css classes for the page content
	 * @return array
	 */
	protected function getHubClasses() {
		$colour = $this->getThemeColour();

		$classes = [
			'wp-mainpage',
			'wp-collaborationhub'
		];
		if ( $colour == 'black' ) {
			$classes = array_merge( $classes, [
				'mw-cklist-square',
				'mw-cktheme'
			] );
		} else {
			$classes = array_merge( $classes, [
				'mw-cklist-square-' . $colour,
				'mw-cktheme-' . $colour
			] );
		}

		return $classes;
	}

	/**
	 * Helper function for fillParserOutput
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getMembersBlock( Title $title, ParserOptions $options ) {
		// deprecated; we need proper list handling to do this properly
		/*
		// Members
		$membersTitle = Title::newFromText( $title->getFullText() . '/' . wfMessage( 'collaborationkit-hub-members-header' )->inContentLanguage()->text() );
		$membersTitleRev = $title ? Revision::newFromTitle( $membersTitle ) : null;
		if ( $membersTitleRev ) {

			$prependiture .= Html::openElement(
				'div',
				[ 'id' => 'wp-header-members' ]
			);
			$prependiture .= Html::element(
				'h2',
				[],
				wfmessage( 'collaborationkit-members-header' )->inContentLanguage()->text()
			);
			$prependiture .= Html::rawElement(
				'div',
				[],
				Revision::newFromTitle( $membersTitle )->getContent()->generateList( $title, $options, $output )
			);

			// BUTTONS
			$membersSignupUrl = SpecialPage::getTitleFor(
				'EditCollaborationHub',
				$membersTitle->getPrefixedUrl()
			)->getLinkUrl();

			$signupButton = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-members-signup' )->inContentLanguage()->text(),
				'href' => $membersSignupUrl,
				'id' => 'wp-signup',
				'flags' => [ 'progressive' ]
			] );
			$seeAllButton = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-hub-members-view' )->inContentLanguage()->text(),
				'href' => $membersTitle->getLinkUrl(),
				'id' => 'wp-seeall'
			] );
			$prependiture .= Html::rawElement(
				'div',
				[ 'id' => 'wp-members-buttons' ],
				$signupButton . ' ' . $seeAllButton
			);

			$prependiture .= Html::closeElement( 'div' );
		}
		*/

		return 'MEMBERS BLOCK HERE (PENDING T140178)';
	}

	/**
	 * Helper function for fillParserOutput
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getParsedIntroduction( Title $title, ParserOptions $options ) {
		global $wgParser;
		$tempOutput = $wgParser->parse( $this->getIntroduction(), $title, $options );

		return $tempOutput->getText();
	}

	/**
	 * Helper function for fillParserOutput
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getParsedFooter( Title $title, ParserOptions $options ) {
		global $wgParser;
		$tempOutput = $wgParser->parse( $this->getFooter(), $title, $options );

		return $tempOutput->getText();
	}

	/**
	 * Helper function for fillParserOutput; the bulk of the actual content
	 * @param Title $title
	 * @param ParserOptions $options
	 * @param ParserOutput &$output
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
			if ( !isset( $item['title'] ) ) {
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
						[ 'class' => 'wp-header-image' ],
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
					$text = $wgParser->parse( $processedText, $title, $options )->getText();
				} else {
					// Parse whatever (else) as whatever
					$contentOutput = $spContent->getParserOutput( $spTitle, $spRev, $options );

					$text = $contentOutput->getText();
				}

				$html .= $text;

				// register as template for stuff
				$output->addTemplate( $spTitle, $spTitle->getArticleId(), $spRev->getId() );
			} else {
				// DO CONTENT FOR NOT YET MADE PAGE
				$html .= Html::rawElement(
					'p',
					[ 'class' => 'wp-missing-note' ],
					wfMessage( 'collaborationkit-hub-missingpage-note' )->inContentLanguage()->parse()
				);

				$linkRenderer = $wgParser->getLinkRenderer();
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
				'class' => 'wp-pagelist-section',
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
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getTableOfContents( Title $title, ParserOptions $options ) {
		$toc = new CollaborationHubTOC();

		return $toc->renderTOC( $this->content, $this->themeColour );
	}

	/**
	 * Generate an image based on what's in 'image', be it an icon or a file
	 * @param string $fallback for what to do for no icons - nothing, random, specific icon...
	 * @param int $size for non-icon images
	 * @param string $seed fallback seed for explicitly something somethinged ones
	 * @return string
	 */
	public function getParsedImage( $image, $size = 200 ) {
		return CollaborationKitIcon::makeIconOrImage( $this->getImage(), $size, 'puzzlepiece' );
	}
}
