<?php

/**
 * Helper class to produce HTML elements containing images for CollaborationKit purposes
 *
 * @file
 */

class CollaborationKitImage {
	/**
	 * Generate an image element from the wiki or the extension
	 *
	 * @param string|null $image The filename (no namespace prefix) or CollaborationKit icon
	 *	identifier (or null to use fallback instead)
	 * @param int $width The width of the image in pixels
	 * @param array $options An array with optional parameters
	 * @param array $options['classes'] Array of element classes to assign
	 * @param Title|string|bool $options['link'] Internal link for the image; default is true (i.e.
	 *	link to its description page). Pass `false` for no link at all. Pass a string to link to a
	 *	page in the manner of an internal wiki link.
	 * @param string $options['colour'] The colour of the icon if using a canned icon
	 * @param string $options['css'] In-line style parameters. Avoid if possible.
	 * @param bool $options['renderAsWikitext'] Should the output be wikitext instead of HTML?
	 *	Defaults to false.
	 * @param string $options['label'] Label to put under image; used for ToC icons
	 * @param string $options['fallback'] If the specified image is null or doesn't exist. Valid
	 *	options are none', a valid icon ID, or an arbitrary string to use a seed. (Note: if you
	 *	specify a label, then that will serve as the fallback.)
	 * @return string HTML elements or wikitext, depending on $options['renderAsWikitext']
	 */
	public static function makeImage( $image, $width, $options = [] ) {

		$cannedIcons = self::getCannedIcons();

		// Setting up options
		$classes = isset( $options['classes'] ) ? $options['classes'] : [];
		$link = isset( $options['link'] ) ? $options['link'] : true;
		$colour = isset( $options['colour'] ) ? $options['colour'] : '';
		$css = isset( $options['css'] ) ? $options['css'] : '';
		$renderAsWikitext = isset( $options['renderAsWikitext'] ) ? $options['renderAsWikitext'] : false;
		$label = isset( $options['label'] ) ? $options['label'] : '';

		if ( !isset( $options['fallback'] ) ) {
			if ( isset( $options['label'] ) ) {
				$options['fallback'] = $options['label'];
			} else {
				$options['fallback'] = 'none';
			}
		}

		// Use fallback icon or random icon if stated image doesn't exist
		if ( $image === null || $image == '' || ( !wfFindFile( $image ) && !in_array( $image, $cannedIcons ) ) ) {
			if ( $options['fallback'] == 'none' ) {
				return '';
			} elseif ( in_array( $options['fallback'], $cannedIcons ) ) {
				$image = $options['fallback'];
			} else {
				$image = $cannedIcons[hexdec( sha1( $options['fallback'] )[0] ) % 27];
			}
		}

		// Are we loading an image file or constructing a div based on an icon class?
		if ( wfFindFile( $image ) ) {
			$imageCode = self::makeImageFromFile( $image, $classes, $width, $link,
				$renderAsWikitext, $label );
		} elseif ( in_array( $image, $cannedIcons ) ) {
			$imageCode = self::makeImageFromIcon( $image, $classes, $width, $colour,
				$link, $renderAsWikitext, $label );
		}

		// Finishing up
		$wrapperAttributes = [ 'class' => $classes, 'style' => $css ];
		$imageBlock = Html::rawElement( 'div', $wrapperAttributes, $imageCode );
		return $imageBlock;
	}

	/**
	 * @return string
	 */
	protected static function makeImageFromFile( $image, $classes, $width, $link,
		$renderAsWikitext, $label ) {
		// This assumes that colours cannot be assigned to images.
		// This is currently true, but who knows what the future might hold!

		global $wgParser;

		$imageObj = wfFindFile( $image );
		$imageTitle = $imageObj->getTitle();
		$imageFullName = $imageTitle->getFullText();

		$wikitext = "[[{$imageFullName}|{$width}px";

		if ( $link === false || $label != '' ) {
			$wikitext .= '|link=]]';
		} elseif ( is_string( $link ) ) {
			$wikitext .= "|link={$link}]]";
		} else {
			$wikitext .= ']]';
		}

		if ( $renderAsWikitext ) {
			return $wikitext;
		} else {
			$imageHtml = $wgParser->parse( $wikitext, $imageTitle, new ParserOptions() )->getText();

			if ( $label != '' ) {
				$imageWrapperCss = "width:{$width}px; max-height:{$width}px; overflow:hidden;";

				$imageHtml = Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-file-image', 'style' => $imageWrapperCss ],
					$imageHtml
				);
				if ( $link !== false ) {
					$imageHtml = self::linkFactory( $imageHtml, $link, $label, $imageObj );
				}
			}

			return $imageHtml;
		}
	}

	/**
	 * @return string
	 */
	protected static function makeImageFromIcon( $image, $classes, $width, $colour, $link,
		$renderAsWikitext, $label ) {
		// Rendering as wikitext with link is not an option here due to unfortunate behavior from Tidy.

		$imageClasses = [ 'mw-ck-icon' ];
		if ( $colour != '' && $colour != 'black' ) {
			$imageClasses[] = 'mw-ck-icon-' . $image . '-' . $colour;
		} else {
			$imageClasses[] = 'mw-ck-icon-' . $image;
		}

		$imageHtml = Html::rawElement(
			'div',
			[ 'class' => $imageClasses, 'style' => "width: {$width}px; height: {$width}px;" ],
			''
		);

		if ( !$renderAsWikitext && $link !== false ) {
			$imageHtml = self::linkFactory( $imageHtml, $link, $label );
		}

		return $imageHtml;
	}

	/**
	 * @return string
	 */
	protected static function linkFactory( $imageHtml, $link, $label, $imageObj = null ) {
		// Important assumption: image is being rendered as HTML and not wikitext.
		if ( $link instanceof Title ) {
			$linkHref = $link->getLinkUrl();
		} elseif ( is_string( $link ) ) {
			$linkHref = Title::newFromText( $link )->getLinkUrl();
		} elseif ( $imageObj !== null ) {
			$linkHref = $imageObj->getTitle()->getLinkUrl();
		} else {
			$linkHref = '#';
		}

		if ( $label != '' ) {
			$imageHtml .= Html::rawElement( 'span', [ 'class' => 'mw-ck-toc-item-label' ], $label );
		}
		return Html::rawElement( 'a', [ 'href' => $linkHref ], $imageHtml );
	}

	/**
	 * @return array All the canned icons in CollaborationKit
	 */
	public static function getCannedIcons() {
		// Keep this synced with the icons listed in the module in extension.json
		return $iconsPreset = [
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
	}
}
