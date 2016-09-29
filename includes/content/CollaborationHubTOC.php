<?php

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
		global $wgParser;
		$linkRenderer = $wgParser->getLinkRenderer();

		$html = Html::openElement( 'div', [ 'class' => 'wp-toc-container' ] );
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'toc-label' ],
			wfMessage( 'collaborationkit-hub-toc-label' )->inContentLanguage()->text()
		);
		$html .= Html::openElement( 'ul' );

		foreach ( $content as $item ) {
			$title = Title::newFromText( $item['title'] );
			$rev = Revision::newFromTitle( $title );

			// TODO sanitise?
			if ( isset( $item['display_title'] ) ) {
				$displayTitle = $item['display_title'];
			} else {
				$displayTitle = $title->getSubpageText();
			}

			if ( isset( $item['image'] ) ) {
				$displayIcon = CollaborationKitIcon::makeIconOrImage( $item['image'], 50, $colour );
			} else {
				$displayIcon = CollaborationKitIcon::makeIconOrImage( $displayTitle, 50, $colour );
			}

			$linkTarget = Title::newFromText( '#' . $this->getToCLinkID( $displayTitle ) );
			$linkDisplay = new HtmlArmor( Html::rawElement(
				'div',
				[],
				$displayIcon . Html::element( 'span', [ 'class' => 'item-label' ], $displayTitle )
			) );
			$link = $linkRenderer->makeLink( $linkTarget, $linkDisplay );

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
	public function renderSubpageToC( $title ) {

	}

	// TOC stuff as was. This is going away.

	/**
	 * Helper function for fillParserOutput; return HTML for a ToC.
	 * @param $title Title for target
	 * @param $type string main or flat or stuff (used as css class)
	 * @return string|null
	 */
	protected function generateToC( Title $title, ParserOutput &$output, $type = 'main' ) {
		// TODO use correct version of text; support PREVIEWS as well as just pulling the content revision
		$rev = Revision::newFromTitle( $title );
		if ( isset( $rev ) ) {
			$sourceContent = $rev->getContent();
			$html = '';

			if ( $rev->getContentModel() == 'CollaborationHubContent' ) {
				$ToCItems = [];

				// Add project mainpage to toc for subpages
				if ( $type != 'main' ) {

					$display = Html::element(
						'span',
						[],
						$sourceContent->getPageName()
					);
					$display = $sourceContent->getImage( 'puzzlepiece', 40 ) . $display;

					$ToCItems[$sourceContent->getPageName()] = [
						Html::rawElement(
							'span',
							[ 'class' => 'wp-toc-projectlabel' ],
							wfMessage( 'collaborationhub-toc-partof' )->inContentLanguage()->text()
						) . Linker::Link( $title, $display ),
						'toc-mainpage'
					];
				}

				foreach ( $sourceContent->getContent() as $item ) {
					$spTitle = Title::newFromText( $item['item'] );
					$spRev = Revision::newFromTitle( $spTitle );

					if ( isset( $spRev ) ) {
						$spContent = $spRev->getContent();
						$spContentModel = $spRev->getContentModel();

						$output->addTemplate( $spTitle, $spTitle->getArticleId(), $spRev->getId() );
					} else {
						$spContentModel = 'none';

						$output->addTemplate( $spTitle, $spTitle->getArticleId(), null );
					}

					// Display name and #id
					$item = $spContentModel == 'CollaborationHubContent' ?
						$spContent->getPageName() : $spTitle->getSubpageText();
					$display = Html::element( 'span', [ 'class' => 'item-label' ], $item );
					while ( isset( $ToCItems[$item] ) ) {
						// Already exists, add a 1 to the end to avoid duplicates
						$item = $item . '1';
					}

					// Link
					if ( $type != 'main' ) {
						// TODO add 'selected' class if already on it
						$link = $spTitle;
					} else {
						$link = Title::newFromText( '#' . htmlspecialchars( $item ) );
					}

					// Icon
					if ( $spContentModel == 'CollaborationHubContent' /* && image is set in $spContent */ ) {
						$display = $spContent->getImage( 'random', 50 ) . $display;
					} else {
						// Use this one as a surrogate because it's not a real hub page; $link can act as seed
						$display = $this->getImage( 'random', 50, $item ) . $display;
					}

					$ToCItems[$item] = [ Linker::Link( $link, $display ), Sanitizer::escapeId( 'toc-' . $spTitle->getSubpageText() ) ];
				}
				$html .= Html::openElement( 'div', [ 'class' => 'wp-toc' ] );

				if ( $type == 'main' ) {
					$html .= Html::rawElement(
						'div',
						[ 'class' => 'toc-label' ],
						wfMessage( 'collaborationkit-toc-label' )->inContentLanguage()->text()
					);
				}

				$html .= Html::openElement( 'ul' );

				foreach ( $ToCItems as $item => $linkJunk ) {
					$html .= Html::rawElement(
						'li',
						[
							'class' => 'wp-toc-item ' . $linkJunk[1] // id info
						],
						$linkJunk[0] // link html string
					);
				}
				$html .= Html::closeElement( 'ul' );
				$html .= '<div class="visualClear"></div>';
				$html .= Html::closeElement( 'div' );

				$html = Html::rawElement(
					'div',
					[ 'class' => 'wp-toc-container' ],
					$html
				);
			} else {
				$html = 'Page not found, ToC not possible';
			}
		} else {
			$html = '';
		}

		return $html;
	}

}
