<?php

/**
 *
 * Helper class to produce HTML elements containing images for CollaborationKit purposes
 *
 */
class CollaborationKitImage {
	/**
	 * Generate an image element from the wiki or the extension
	 * @param $image string|null The filename (no namespace prefix) or CollaborationKit icon
	 *	identifier (or null to use fallback instead)
	 * @param $width int The width of the image in pixels
	 * @param $options array An array with optional parameters
	 * @param $options['classes'] array Array of element classes to assign
	 * @param $options['link'] Title|string|bool Internal link for the image; default is true (i.e.
	 *	link to its description page). Pass `false` for no link at all. Pass a string to link to a
	 *	page in the manner of an internal wiki link.
	 * @param $options['colour'] string The colour of the icon if using a canned icon
	 * @param $options['css'] string In-line style parameters. Avoid if possible.
	 * @param $options['renderAsWikitext'] bool Should the output be wikitext instead of HTML?
	 *	Defaults to false.
	 * @param $options['label'] string Label to put under image; used for ToC icons
	 * @param $options['fallback'] string If the specified image is null or doesn't exist. Valid
	 *	options are none', a valid icon ID, or an arbitrary string to use a seed. (Note: if you
	 *	specify a label, then that will serve as the fallback.)
	 * @return string HTML elements or wikitext, depending on $options['renderAsWikitext']
	 */
	public static function makeImage( $image, $width, $options = [] ) {

		// Default options
		if ( !isset( $options[ 'classes' ] ) ) {
			$options[ 'classes' ] = [];
		}
		if ( !isset( $options[ 'link' ] ) ) {
			$options[ 'link' ] = true;
		}
		if ( !isset( $options[ 'colour' ] ) ) {
			$options[ 'colour' ] = '';
		}
		if ( !isset( $options[ 'css' ] ) ) {
			$options[ 'css' ] = '';
		}
		if ( !isset( $options[ 'renderAsWikitext' ] ) ) {
			$options[ 'renderAsWikitext' ] = false;
		}
		if ( !isset( $options[ 'label' ] ) ) {
			$options[ 'label' ] = '';
		}
		if ( !isset( $options[ 'fallback' ] ) ) {
			if ( isset( $options[ 'label' ] ) ) {
				$options[ 'fallback' ] = $options[ 'label' ];
			} else {
				$options[ 'fallback' ] = 'none';
			}
		}

		$cannedIcons = self::getCannedIcons();

		// Use fallback icon or random icon if stated image doesn't exist
		if ( $image === null || $image == '' || ( !wfFindFile( $image ) && !in_array( $image, $cannedIcons ) ) ) {
			if ( $options[ 'fallback' ] == 'none' ) {
				return '';
			} elseif ( in_array( $options[ 'fallback' ], $cannedIcons ) ) {
				$image = $options[ 'fallback' ];
			} else {
				$image = $cannedIcons[ hexdec( sha1( $options[ 'fallback' ] )[0] ) % 27];
			}
		}

		// Are we loading an image file or constructing a div based on an icon class?
		if ( wfFindFile( $image ) ) {
			$imageCode = self::makeImageFromFile( $image, $options[ 'classes' ], $width, $options[ 'link' ],
				$options[ 'renderAsWikitext' ], $options[ 'label' ] );
		} elseif ( in_array( $image, $cannedIcons ) ) {
			$imageCode = self::makeImageFromIcon( $image, $options[ 'classes' ], $width, $options[ 'colour' ],
				$options[ 'link' ], $options[ 'renderAsWikitext' ], $options[ 'label' ] );
		}

		// Finishing up
		$wrapperAttributes = [ 'class' => $options[ 'classes' ], 'style' => $options[ 'css' ] ];
		$imageBlock = Html::rawElement( 'div', $wrapperAttributes, $imageCode );
		return $imageBlock;
	}

	protected static function makeImageFromFile( $image, $classes, $width, $link,
		$renderAsWikitext, $label ) {
		// This assumes that colours cannot be assigned to images.
		// This is currently true, but who knows what the future might hold!

		$imageObj = wfFindFile( $image );
		$imageFullName = $imageObj->getTitle()->getFullText();

		if ( $renderAsWikitext ) {
			$wikitext = "[[{$imageFullName}|{$width}px";

			if ( $link === false ) {
				$wikitext .= "|nolink]]";
			} elseif ( is_string( $link ) ) {
				$wikitext .= "|link={$link}]]";
			} else {
				$wikitext .= "]]";
			}

			return $wikitext;

		} else {
			$imageHtml = $imageObj->transform( [ 'width' => $width ] )->toHtml();

			if ( $label != '' ) {
				$imageWrapperCss = "width:{$width}px; max-height:{$width}px; overflow:hidden;";

				$imageHtml = Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-file-image', 'style' => $imageWrapperCss ],
					$imageHtml
				);
			}

			if ( $link !== false ) {
				$imageHtml = self::linkFactory( $imageHtml, $link, $label, $imageObj );
			}

			return $imageHtml;
		}
	}

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