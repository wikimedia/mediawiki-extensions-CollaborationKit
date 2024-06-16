<?php

/**
 * Content handler for CollaborationHubContent.
 *
 * We extend TextContentHandler instead of JsonContentHandler since
 * we do not display this as JSON code except upon request.
 *
 * @file
 */

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\TextContentHandler;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Json\FormatJson;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use OOUI\BlankTheme;
use OOUI\Theme;

class CollaborationHubContentHandler extends TextContentHandler {

	const FORMAT_WIKI = 'text/x-collabkit';

	/**
	 * @param string $modelId
	 * @param string[] $formats
	 */
	public function __construct(
		$modelId = 'CollaborationHubContent',
		$formats = [ CONTENT_FORMAT_JSON, CONTENT_FORMAT_TEXT, self::FORMAT_WIKI ]
	) {
		// text/x-collabkit is a format for lists similar to <gallery>.
		// CONTENT_FORMAT_TEXT is for back-compat with old revs. Could be removed.

		// @todo Ideally, we'd have the preferred format for editing be
		// self::FORMAT_WIKI and the preferred format for db be
		// CONTENT_FORMAT_JSON. Unclear if that's possible.
		parent::__construct( $modelId, $formats );
	}

	/**
	 * @inheritDoc
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$output
	) {
		'@phan-var CollaborationHubContent $content';
		/** @var CollaborationHubContent $content */
		$page = $cpoParams->getPage();
		$titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
		$title = $titleFactory->newFromPageReference( $page );
		$revId = $cpoParams->getRevId();
		$options = $cpoParams->getParserOptions();

		$parser = MediaWikiServices::getInstance()->getParser();
		$content->decode();

		OutputPage::setupOOUI();

		// Dummy parse intro and footer to get categories and page info for the actual
		// content of *this* page, essentially setting up our real ParserOutput
		$output = $parser->parse(
			$content->getIntroduction() . $content->getFooter(),
			$title,
			$options,
			true,
			true,
			$revId
		);

		$parser->addTrackingCategory( 'collaborationkit-hub-tracker' );

		// Let's just assume we'll probably need this...
		// (tells our ParserOutputPostCacheTransform hook to look for post-cache buttons etc)
		$output->setExtensionData( 'ck-editmarkers', true );

		// Change $options a bit for the rest of this
		// We may or may not want limit reporting for every piece; we can put this back on
		// later if it turns out we actually do (and only disable it for the header/footer,
		// where it should already be included per the above, I think?)
		$options->enableLimitReport( false );

		$html = '';

		// If error, then bypass all this and just show the offending JSON

		if ( $content->displaymode == 'error' ) {
			$html = '<div class=errorbox>'
			. wfMessage( 'collaborationkit-hub-invalid' )->escaped()
			. "</div>\n<pre>"
			. $content->errortext
			. '</pre>';
			$output->setText( $html );
		} else {
			// set up hub with theme stuff
			$html .= Html::openElement(
				'div',
				[ 'class' => $content->getHubClasses() ]
			);
			// get page image
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-image' ],
				$content->getParsedImage( $content->getImage(), 200 )
			);
			// get members list
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-members' ],
				$content->getMembersBlock( $title, $options, $output )
			);
			// get parsed intro
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-intro' ],
				$content->getParsedIntroduction( $title, $options )
			);
			// get announcements
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-announcements' ],
				$content->getParsedAnnouncements( $title, $options )
			);
			// get table of contents
			if ( count( $content->getContent() ) > 0 ) {
				$html .= Html::rawElement(
					'div',
					[ 'class' => [ 'mw-ck-hub-toc', 'toc' ] ],
					$content->getTableOfContents( $title, $options )
				);
			}

			$html .= Html::element( 'div', [ 'style' => 'clear:both' ] );

			// get transcluded content
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-ck-hub-content' ],
				$content->getParsedContent( $title, $options, $output )
			);

			$html .= Html::element( 'div', [ 'style' => 'clear:both' ] );

			// get main footer: bottom text under the content
			$footerText = $content->getParsedFooter( $title, $options );
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
					$content->getSecondFooter( $title )
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
	 * @param Title $title Page to check
	 * @return bool
	 */
	public function canBeUsedOn( Title $title ) {
		global $wgCollaborationHubAllowedNamespaces;

		$namespace = $title->getNamespace();
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		return isset( $wgCollaborationHubAllowedNamespaces[$namespace] ) &&
			$wgCollaborationHubAllowedNamespaces[$namespace] &&
			$namespaceInfo->hasSubpages( $namespace );
	}

	/**
	 * Constructs a CollaborationHubContent object. Does not perform any
	 * validation, as that is done at a later step (to allow for outputting of
	 * invalid content for debugging purposes.)
	 *
	 * @param string $text
	 * @param string|null $format
	 * @return CollaborationHubContent
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		if ( $format === self::FORMAT_WIKI ) {
			$data = CollaborationHubContent::convertFromHumanEditable( $text );
			$text = FormatJson::encode( $data );
		}
		$content = new CollaborationHubContent( $text );
		// Deliberately not validating at this step; validation is done later.
		return $content;
	}

	/**
	 * Serializes the CollaborationHubContent object.
	 *
	 * @param Content|CollaborationHubContent $content
	 * @param string|null $format
	 * @return mixed
	 */
	public function serializeContent( Content $content, $format = null ) {
		if ( $format === self::FORMAT_WIKI ) {
			return $content->convertToHumanEditable();
		}
		return parent::serializeContent( $content, $format );
	}

	/**
	 * @return CollaborationHubContent
	 */
	public function makeEmptyContent() {
		$empty = <<<JSON
			{
				"display_name": "",
				"introduction": "",
				"footer": "",
				"content": []
			}
JSON;
		return new CollaborationHubContent( $empty );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'CollaborationHubContent';
	}

	/**
	 * @return bool
	 */
	public function isParserCacheSupported() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function supportsRedirects() {
		return true;
	}

	/**
	 * Turns CollaborationHubContent page into redirect
	 *
	 * Note that wikitext redirects are created, as generally, this content model
	 * is used in namespaces that support wikitext, and wikitext redirects are
	 * expected.
	 *
	 * @param Title $destination The page to redirect to
	 * @param string $text Text to include in the redirect.
	 * @return Content
	 */
	public function makeRedirectContent( Title $destination, $text = '' ) {
		$handler = MediaWikiServices::getInstance()
			->getContentHandlerFactory()
			->getContentHandler( CONTENT_MODEL_WIKITEXT );
		return $handler->makeRedirectContent( $destination, $text );
	}

	/**
	 * Edit a Collaboration Hub via the edit API
	 * @param Title $title
	 * @param string $displayName
	 * @param string $image
	 * @param string $colour
	 * @param string $introduction
	 * @param string $footer
	 * @param array $content
	 * @param string $summary Message key for edit summary
	 * @param IContextSource $context The calling context
	 * @return Status
	 */
	public static function edit( Title $title, $displayName, $image, $colour,
		$introduction, $footer, $content, $summary, IContextSource $context
	) {
		$contentBlock = [
			'display_name' => $displayName,
			'introduction' => $introduction,
			'footer' => $footer,
			'image' => $image,
			'colour' => $colour,
			'content' => $content
		];

		// TODO Do content

		$jsonText = FormatJson::encode( $contentBlock );
		if ( $jsonText === null ) {
			return Status::newFatal( 'collaborationkit-hub-edit-tojsonerror' );
		}

		// Ensure that a valid context is provided to the API in unit tests
		$der = new DerivativeContext( $context );
		$request = new DerivativeRequest(
			$context->getRequest(),
			[
				'action' => 'edit',
				'title' => $title->getFullText(),
				'contentmodel' => 'CollaborationHubContent',
				'text' => $jsonText,
				'summary' => $summary,
				'token' => $context->getUser()->getEditToken(),
			],
			true // Treat data as POSTed
		);
		$der->setRequest( $request );

		try {
			$api = new ApiMain( $der, true );
			$api->execute();
		} catch ( ApiUsageException $e ) {
			return Status::newFatal(
				$context->msg( 'collaborationkit-hub-edit-apierror',
				$e->getMessageObject() ) );
		}
		return Status::newGood();
	}

	/**
	 * Post-parse out our button markers for uncachable permissions-dependent actions and stuff
	 *
	 * @param ParserOutput $parserOutput
	 * @param string &$text The text being transformed, before core transformations are done
	 * @param array &$options The options array being used for the transformation.
	 *
	 * @return bool
	 */
	public static function onParserOutputPostCacheTransform( $parserOutput, &$text, &$options ) {
		// Maybe not blindly do this on every getText ever
		if ( !$parserOutput->getExtensionData( 'ck-editmarkers' ) ) {
			return true;
		}

		Theme::setSingleton( new BlankTheme() );

		// This is a tad dumb, but if it doesn't follow this format exactly it didn't come
		// from here anyway. Or we broke it. Either or.
		$regex = '#<ext:ck:editmarker page="(.*?)"target="(.*?)"message="(.*?)"link="(.*?)"'
			. 'classes="(.*?)"icon="(.*?)"framed="(.*?)"primary="(.*?)"'
			. '(.*?)/>#s';
		$text = preg_replace_callback(
			$regex,
			static function ( $m ) {
				$user = RequestContext::getMain()->getUser();
				$permissionManager = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();

				$currentPage = Title::newFromText( htmlspecialchars_decode( $m[1] ) );
				$targetPage = Title::newFromText( htmlspecialchars_decode( $m[2] ) );

				if ( $permissionManager->userCan( 'edit', $user, $targetPage ) ) {
					$message = htmlspecialchars_decode( $m[3] );

					// more checks for various combinations of pages we need to
					// edit/create
					// in particular we need to be able to both create a new
					// subpage AND edit currentpage to flat-out add a new
					// feature...
					// create right check covered throught edit right check
					if ( $message === 'collaborationkit-hub-addpage' &&
						!$permissionManager->userCan( 'edit', $user, $currentPage )
					) {
						return '';
					}

					$link = $m[4];
					$classes = $m[5];

					$icon = null;
					if ( $m[6] !== '0' ) {
						$icon = htmlspecialchars_decode( $m[6] );
					}

					$framed = false;
					if ( $m[7] === '1' ) {
						$framed = true;
					}

					$flags = [ 'progressive' ];
					if ( $m[8] === '1' ) {
						$flags[] = 'primary';
					}

					return new OOUI\ButtonWidget( [
						'label' => wfMessage( $message )->inContentLanguage()->text(),
						'href' => $link,
						'framed' => $framed,
						'icon' => $icon,
						'flags' => $flags,
						'classes' => [ $classes ]
					] );
				}

				return '';
			},
			$text
		);

		// missing page message
		$text = preg_replace_callback(
			'#<ext:ck:missingfeature-note target="(.*?)"(.*?)/>#s',
			static function ( $m ) {
				$user = RequestContext::getMain()->getUser();
				$permissionManager = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();
				$targetPage = Title::newFromText( htmlspecialchars_decode( $m[1] ) );

				if ( $permissionManager->userCan( 'create', $user, $targetPage ) ) {
					return wfMessage( 'collaborationkit-hub-missingpage-note' )
						->inContentLanguage()
						->parse();
				} else {
					return wfMessage( 'collaborationkit-hub-missingpage-protected-note' )
						->inContentLanguage()
						->parse();
				}
			},
			$text
		);
	}
}
