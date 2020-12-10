<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

/**
 * Helper class to generate table of contents for CollaborationHubContent.
 *
 * @file
 */

class CollaborationHubTOC {

	/** @var array ids/links for ToC items that have been used already */
	protected $tocLinks;

	/**
	 * Get unique id for ToC Link/header
	 *
	 * @param string $header
	 * @return string
	 */
	public function getToCLinkID( $header ) {
		$link = Sanitizer::escapeIdForLink( htmlspecialchars( $header ) );
		$link2 = $link;
		$linkCounter = 1;
		while ( in_array( $link2, $this->tocLinks ) ) {
			$link2 = $link . '_' . $linkCounter;
		}
		$this->tocLinks[] = $link2;
		return $link2;
	}

	public function resetToCLinks() {
		$this->tocLinks = [];
	}

	public function __construct() {
		$this->resetToCLinks();
	}

	/**
	 * ToC rendering for CollaborationHubContent
	 *
	 * @param array $content Array block from CollaborationHubContent
	 * @return string HTML
	 */
	public function renderToC( $content ) {
		$html = Html::openElement( 'div', [ 'class' => 'mw-ck-toc-container' ] );
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'mw-ck-toc-label' ],
			wfMessage( 'collaborationkit-hub-toc-label' )
				->inContentLanguage()
				->text()
		);
		$html .= Html::openElement( 'ul' );

		foreach ( $content as $item ) {
			if ( $item['title'] == '' ) {
				continue;
			}
			$title = Title::newFromText( $item['title'] );
			if ( isset( $item['displayTitle'] ) ) {
				$displayTitle = $item['displayTitle'];
			} else {
				$displayTitle = $title->getSubpageText();
			}
			$linkTarget = Title::newFromText( '#'
				. $this->getToCLinkID( $displayTitle )
			);
			$image = $item['image'] ?? null;

			$link = CollaborationKitImage::makeImage(
				$image,
				50,
				[ 'link' => $linkTarget, 'label' => $displayTitle ]
			);

			$html .= Html::rawElement(
				'li',
				[ 'class' => 'mw-ck-toc-item' ],
				$link
			);
		}

		$html .= Html::closeElement( 'ul' );
		$html .= Html::closeElement( 'div' );
		return $html;
	}

	/**
	 * ToC rendering for other pages such as subpages
	 *
	 * @param Title $title Hub the ToC is generated from
	 * @return string html
	 */
	public function renderSubpageToC( Title $title ) {
		// We assume $title is sane. This is supposed to be called with a $title
		// gotten from CollaborationHubContent::getParentHub, which already
		// checks if it is.
		$rev = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $title );
		/** @var CollaborationHubContent $content */
		$content = $rev->getContent( SlotRecord::MAIN );
		$colour = $content->getThemeColour();
		$image = $content->getImage();

		$html = Html::openElement( 'div', [ 'class' => "mw-ck-theme-$colour" ] );
		$html .= Html::openElement( 'div', [ 'class' => [ 'mw-ck-subpage-toc', 'toc' ] ] );

		// ToC label

		$label = Html::rawElement(
			'div',
			[ 'class' => 'mw-ck-toc-label' ],
			Html::rawElement(
				'span',
				[],
				wfMessage( 'collaborationkit-subpage-toc-label' )
					->inContentLanguage()
					->escaped()
			)
		);

		// hubpage
		$name = $content->getDisplayName() == '' ?
			$title->getText() : $content->getDisplayName();
		$linkRenderer = MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
		$link = $linkRenderer->makeLink( $title, $name );
		$hubPage = Html::rawElement(
			'div',
			[ 'class' => 'mw-ck-toc-subpage-hub' ],
			$link
		);

		$html .= Html::rawElement( 'div', [ 'class' => 'mw-ck-toc-header' ], $label . $hubPage );

		// Contents
		$html .= Html::openElement( 'ul', [ 'class' => 'mw-ck-toc-contents' ] );

		foreach ( $content->getContent() as $item ) {
			$itemTitle = Title::newFromText( $item['title'] );

			if ( isset( $item['display_title'] ) ) {
				$itemDisplayTitle = $item['display_title'];
			} else {
				$itemDisplayTitle = $itemTitle->getSubpageText();
			}
			$itemImage = $item['image'] ?? $itemDisplayTitle;

			$itemLink = CollaborationKitImage::makeImage(
				$itemImage,
				16,
				[ 'link' => $itemTitle, 'label' => $itemDisplayTitle, 'colour' => $colour ]
			);

			$html .= Html::rawElement(
				'li',
				[ 'class' => 'mw-ck-toc-item' ],
				$itemLink
			);
		}

		$html .= Html::closeElement( 'ul' );
		$html .= Html::closeElement( 'div' );
		// @phan-suppress-next-line PhanPluginDuplicateAdjacentStatement
		$html .= Html::closeElement( 'div' );
		return $html;
	}
}
