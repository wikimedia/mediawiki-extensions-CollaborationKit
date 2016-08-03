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
			"display_title": "What to show on the page (defaults to {{SUBPAGENAME}} otherwise)",
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
			$this->getParsedImage( $this->getImage(), 150 )
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
			$this->getTableofContents( $title, $options )
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
		OutputPage::setupOOUI();
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
		$membersTitle = Title::newFromText( $title->getFullText() . '/' . wfMessage( 'collaborationkit-members-header' )->inContentLanguage()->text() );
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
				'label' => wfMessage( 'collaborationkit-members-signup' )->inContentLanguage()->text(),
				'href' => $membersSignupUrl,
				'id' => 'wp-signup',
				'flags' => [ 'progressive' ]
			] );
			$seeAllButton = new OOUI\ButtonWidget( [
				'label' => wfMessage( 'collaborationkit-members-view' )->inContentLanguage()->text(),
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
		// global $wgParser;
		return $html = 'CONTENT APPEARS HERE (PENDING T136785)';

		/*
		$linkRenderer = $wgParser->getLinkRenderer();
		$list = '';

		foreach ( $this->getContent() as $item ) {

			// TODO check if subpage exists

			// get collaborationhubcontent object for the subpage and stuff
			$spTitle = Title::newFromText( $item['item'] );
			$spRev = Revision::newFromTitle( $spTitle );
			$list .= Html::openElement( 'div', [ 'class' => 'wp-pagelist-section' ] );

			// So the ToC has something to link to
			$tocLinks = [];

			if ( isset( $spRev ) ) {
				$spContent = $spRev->getContent();
				$spContentModel = $spRev->getContentModel();
				// TODO Check if it's even a hub?

				if ( $spContentModel == 'CollaborationHubContent' ) {
					$spPage = $spContent->getDisplayName();
				} else {
					$spPage = $spTitle->getSubpageText();
				}
			} else {
				$spPage = $spTitle->getSubpageText();
			}
			$spPageLink = Sanitizer::escapeId( htmlspecialchars( $spPage ) );

			// Replicate generateToC's handling of duplicates
			while ( in_array( $spPageLink, $tocLinks ) ) {
				$spPageLink .= '1';
			}
			$tocLinks[] = $spPageLink;

			if ( isset( $spRev ) ) {
				// add content block to listContent
				// TODO sanitise?

				// TODO Shouldn't this be using Linker::makeHeadline?
				$headline = Html::rawElement(
					'span',
					[ 'class' => 'mw-headline', 'id' => $spPageLink ],
					$spPage
				);

				$sectionLinks = [
					'viewLink' => $linkRenderer->makeLink(
						$spTitle,
						wfMessage( 'view' )->inContentLanguage()->text()
					)
				];
				if ( $spTitle->userCan( 'edit' ) ) {
					$sectionLinks['edit'] = $linkRenderer->makeLink(
						SpecialPage::getTitleFor(
							'EditCollaborationHub',
							$spTitle->getPrefixedURL()
						),
						wfMessage( 'edit' )->inContentLanguage()->text()
					);
				}
				// TODO figure out why this one isn't showing up
				if ( $title->userCan( 'edit' ) ) {
					$sectionLinks['delete'] = $linkRenderer->makeLink(
						SpecialPage::getTitleFor(
							'EditCollaborationHub',
							$title->getPrefixedURL()
						),
						wfMessage( 'collabkit-list-delete' )->inContentLanguage()->text()
					);
				}
				$sectionLinksHtml = '';
				foreach ( $sectionLinks as $link => $linkString ) {
					$sectionLinksHtml .= $this->editSectionLink( $linkString );
				}

				Html::rawElement(
					'span',
					[ 'class' => 'mw-editsection' ],
					$sectionLinksHtml
				);

				$list .= Html::rawElement(
					'h2',
					[],
					$headline . $sectionLinksHtml
				);

				// TODO wrap in stuff
				// TODO REPLACE ALL THIS WITH PROPER AGNOSTIC HANDLING SOMEHOW
				if ( $spContentModel == 'CollaborationHubContent' ) {
					// TODO wrap in stuff
					$list .= $spContent->getParsedIntroduction( $title, $options );
					// TODO wrap in stuff; limit number of things to output for lists, length for wikitext
					$list .= $spContent->getParsedContent( $title, $options, $output );
				} else {
					// Oh shit it's not a hubpage
					if ( $spContentModel == 'wikitext' ) {
						$list .= $spContent->getParserOutput( $spTitle )->getText();
					} else {
						// Oh shit, what?
					}
				}
			} else {
				// TODO Replace this with a button to special:createcollaborationhub/title
				$list .= Html::openElement(
					'h2',
					[ 'class' => 'wp-header-missing' ]
				);
				$list .= Html::element(
					'span',
					[ 'id' => $spPageLink, 'class' => 'mw-headline' ],
					$spTitle->getSubpageText()
				);

				$list .= $this->editSectionLink( $linkRenderer->makeLink(
					SpecialPage::getTitleFor(
						'EditCollaborationHub',
						$title->getPrefixedURL()
					),
					wfMessage( 'collabkit-list-delete' )->inContentLanguage()->text()
				) );
				$list .= Html::closeElement( 'h2' );

				$list .= Html::rawElement(
					'p',
					[ 'class' => 'wp-missing-note' ],
					wfMessage( 'collaborationkit-missing-note' )->inContentLanguage()->parse()
				);

				$list .= new OOUI\ButtonWidget( [
					'label' => wfMessage( 'collaborationkit-create-subpage' )->inContentLanguage()->text(),
					'href' => SpecialPage::getTitleFor(
							'EditCollaborationHub',
							$spTitle->getPrefixedURL()
						)->getLinkURL()
				] );
			}
			$list .= Html::closeElement( 'div' );

			// Register page as dependency
			if ( isset( $spRev ) ) {
				$output->addTemplate( $spTitle, $spTitle->getArticleId(), $spRev->getId() );
			} else {
				$output->addTemplate( $spTitle, $spTitle->getArticleId(), null );
			}
		}
		$html .= $ToC . $list;

		return Html::rawElement(
			'div',
			[ 'class' => 'wp-content' ],
			$html
		);
		*/
	}

	/**
	 * Helper function for fillParserOutput for making editsection links in headers
	 * @param $link string html of the link itself
	 * @return string html
	 */
	protected function editSectionLink( $link ) {
		$html = Html::openElement(
			'span',
			[ 'class' => 'mw-editsection' ]
		);
		$html .= Html::element(
			'span',
			[ 'class' => 'mw-editsection-bracket' ],
			'['
		);
		$html .= Html::rawElement(
			'span',
			[],
			$link
		);
		$html .= Html::element(
			'span',
			[ 'class' => 'mw-editsection-bracket' ],
			']'
		);
		$html .= Html::closeElement( 'span' );

		return $html;
	}

	/**
	 * Helper function for fillParserOutput: the table of contents
	 * @param Title $title
	 * @param ParserOptions $options
	 * @return string
	 */
	protected function getTableofContents( Title $title, ParserOptions $options ) {
		// This is going to be moved into its own class, and cleaning it up here is too much effort right now.
		// Restoring placeholder.
		$html = '<div>TOC APPEARS HERE (PENDING T140170)</div>';

		return $html;
	}

	/**
	 * Generates html for canned icons
	 * @param string $icon data: either an icon id or anything to use as a seed
	 * @return string
	 */
	protected function makeIcon( $icon ) {
		// Keep this synced with the icons listed in the module in extension.json
		$iconsPreset = [
			// Randomly selectable items
			'book',
			'circlestar',
			'clock',
			'community',
			'contents',
			'die',
			'edit',
			'eye',
			'flag',
			'funnel',
			'gear',
			'heart',
			'journal',
			'key',
			'link',
			'map',
			'menu',
			'newspaper',
			'ol',
			'page',
			'paperclip',
			'puzzlepiece',
			'ribbon',
			'rocket',
			'star',
			'sun',
			'ul',

			'addimage',
			'addmapmarker',
			'addquote',
			'bell',
			'circleline',
			'circletriangle',
			'circlex',
			'discussion',
			'download',
			'editprotected',
			'gallery',
			'image',
			'lock',
			'mail',
			'mapmarker',
			'message',
			'messagenew',
			'messagescary',
			'move',
			'nowiki',
			'pagechecked',
			'pageribbon',
			'pagesearch',
			'print',
			'quotes',
			'search',
			'starmenu',
			'translate',
			'trash',
			'user'
		];
		// if preset or other logical class name, just set class; we allow non-preset ones for on-wiki flexibility?
		if ( $icon !== null && in_array( $icon, $iconsPreset ) ) {
			$class = Sanitizer::escapeClass( $icon );
		} else {
			// Choose random class name using $icon value as seed
			$class = $iconsPreset[ hexdec( sha1( $icon )[0] ) % 27];
		}

		$colour = $this->getThemeColour();
		if ( $colour == 'black' ) {
			$colorSuffix = '';
		} else {
			$colorSuffix = '-' . $colour;
		}

		return Html::element( 'div', [ 'class' => 'mw-ckicon mw-ckicon-' . $class .  $colorSuffix ] );
	}

	/**
	 * Generate an image based on what's in 'image', be it an icon or a file
	 * @param string $fallback for what to do for no icons - nothing, random, specific icon...
	 * @param int $size for non-icon images
	 * @param string $seed fallback seed for explicitly something somethinged ones
	 * @return string
	 */
	public function getParsedImage( $fallback = 'none', $size = 50, $seed = null ) {
		if ( $seed === null ) {
			$image = $this->getImage();

			if ( $image == '' || $image == '-' ) {
				if ( $fallback == 'none' ) {
					return '';
				} elseif ( $fallback == 'random' ) {
					return $this->makeIcon( $this->getDisplayName() );
				} else {
					// Maybe they want a specific one?
					return $this->makeIcon( $fallback );
				}
			}
			if ( wfFindFile( $image ) ) {
				return Html::rawElement(
					'div',
					[ 'class' => 'file-image' ],
					wfFindFile( $image )->transform( [ 'width' => $size ] )->toHtml()
				);
			} else {
				return $this->makeIcon( $image );
			}
		} else {
			// No image data etc; use seed
			return $this->makeIcon( $seed );
		}

		// TODO make it handle/return error/do something besides just selecting a random one when file doesn't exist/image key not found?
	}
}
