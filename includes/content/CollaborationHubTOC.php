<?php

use MediaWiki\MediaWikiServices;

class CollaborationHubTOC {

	/** @var $tocLinks array ids/links for ToC items that have been used already */
	protected $tocLinks;

	/**
	 * Get unique id for ToC Link/header
	 * @param $header
	 * @return string
	 */
	public function getToCLinkID( $header ) {
		$link = Sanitizer::escapeId( htmlspecialchars( $header ) );
		$link2 = $link;
		$linkCounter = 1;
		while ( in_array( $link2, $this->tocLinks ) ) {
			$link2 = $link . '_' . $linkCounter;
			$spPageLinkCounter++;
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
	 * ToC rendering for hub
	 * @param $content array block from collaborationhub
	 * @param $colour string variable from collaborationhub content
	 * @return string html
	 */
	public function renderToC( $content, $colour ) {
		$html = Html::openElement( 'div', [ 'class' => 'wp-toc-container' ] );
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'toc-label' ],
			wfMessage( 'collaborationkit-hub-toc-label' )->inContentLanguage()->text()
		);
		$html .= Html::openElement( 'ul' );

		foreach ( $content as $item ) {
			$title = Title::newFromText( $item['title'] );

			if ( isset( $item['display_title'] ) ) {
				$displayTitle = $item['display_title'];
			} else {
				$displayTitle = $title->getSubpageText();
			}
			$linkTarget = Title::newFromText( '#' . $this->getToCLinkID( $displayTitle ) );
			$image = isset( $item['image'] ) ? $item['image'] : $displayTitle;

			$link = $this->renderItem( $linkTarget, $displayTitle, $image, $colour, 50 );

			$html .= Html::rawElement(
				'li',
				[ 'class' => 'wp-toc-item' ],
				$link
			);
		}

		$html .= Html::closeElement( 'ul' );
		$html .= Html::closeElement( 'div' );
		return $html;
	}

	/**
	 * ToC rendering for non-hubs
	 * @param $title Title of hub the ToC is generated off
	 * @return string html
	 */
	public function renderSubpageToC( Title $title ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		// We assume $title is sane. This is supposed to be called with a $title gotten from CollaborationHubContent::getParentHub, which already checks if it is.
		$rev = Revision::newFromTitle( $title );
		$content = $rev->getContent();
		$colour = $content->getThemeColour();
		$image = $content->getImage();

		$html = Html::openElement( 'div', [ 'class' => "wp-subpage-toc mw-cktheme-$colour" ] );

		// ToC label
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'toc-label' ],
			Html::rawElement(
				'span',
				[],
				wfMessage( 'collaborationkit-subpage-toc-label' )->inContentLanguage()->text()
			)
		);

		// hubpage
		$link = $this->renderItem( $title, $content->getDisplayName(), $image, $colour, 16 );
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'toc-subpage-hub' ],
			$link
		);

		// Contents
		$html .= Html::openElement( 'ul', [ 'class' => 'toc-contents' ] );

		foreach ( $content->getContent() as $item ) {
			$itemTitle = Title::newFromText( $item['title'] );

			if ( isset( $item['display_title'] ) ) {
				$itemDisplayTitle = $item['display_title'];
			} else {
				$itemDisplayTitle = $itemTitle->getSubpageText();
			}
			$itemImage = isset( $item['image'] ) ? $item['image'] : $itemDisplayTitle;

			$itemLink = $this->renderItem( $itemTitle, $itemDisplayTitle, $itemImage, $colour, 16 );

			$html .= Html::rawElement(
				'li',
				[ 'class' => 'wp-toc-item' ],
				$itemLink
			);
		}

		$html .= Html::closeElement( 'ul' );
		$html .= Html::closeElement( 'div' );
		return $html;
	}

	/**
	 * Get item for ToC - link with icon and label as contents
	 * @param $title Title for target
	 * @param $text string diplay text for title
	 * @param $image string seed for makeIconOrImage
	 * @param $imageColour string colour id
	 * @param $imageSize int size
	 * @return string html
	 */
	protected function renderItem( Title $title, $text, $image, $imageColour, $imageSize ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$icon = CollaborationKitIcon::makeIconOrImage( $image, $imageSize, $imageColour );

		$linkContent = new HtmlArmor( Html::rawElement(
			'div',
			[],
			$icon . Html::element( 'span', [ 'class' => 'item-label' ], $text )
		) );
		return $link = $linkRenderer->makeLink( $title, $linkContent );
	}
}
