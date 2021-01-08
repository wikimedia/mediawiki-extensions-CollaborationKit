<?php

/**
 * Helper class to produce HTML elements containing images for CollaborationKit
 * purposes.
 *
 * @file
 */

use MediaWiki\MediaWikiServices;

class CollaborationKitImage {
	/**
	 * Generate an image element from the wiki or the extension
	 *
	 * @param string|null $image The filename (no namespace prefix) or
	 *  CollaborationKit icon identifier (or null to use fallback instead)
	 * @param int $width The width of the image in pixels
	 * @param array $options An array with optional parameters:
	 * - array $options['classes'] Array of element classes to assign
	 * - Title|string|bool $options['link'] Internal link for the image;
	 *  default is true (i.e. link to its description page). Pass `false` for no
	 *  link at all. Pass a string to link to a page in the manner of an
	 *  internal wiki link.
	 * - string $options['colour'] The colour of the icon if using a canned icon
	 * - string $options['css'] In-line style parameters. Avoid if possible.
	 * - bool $options['renderAsWikitext'] Should the output be wikitext
	 *  instead of HTML? Defaults to false.
	 * - string $options['label'] Label to put under image; used for ToC icons
	 * - string $options['fallback'] If the specified image is null or
	 *  doesn't exist. Valid options are 'none', a valid icon ID, or an arbitrary
	 *  string to use a seed. (Note: if you specify a label, then that will
	 *  serve as the fallback.)
	 * - bool $options['optimizeForSquare'] Fetch an image such that it's
	 *  ideal for shoving into a square frame. Default is false. Images with
	 *  labels always get optimzied for squares.
	 * @return string HTML elements or wikitext, depending on
	 *  $options['renderAsWikitext']
	 */
	public static function makeImage( $image, $width, $options = [] ) {
		$cannedIcons = self::getCannedIcons();

		// Setting up options
		$classes = $options['classes'] ?? [];
		$link = $options['link'] ?? true;
		$colour = $options['colour'] ?? '';
		$css = $options['css'] ?? '';
		$renderAsWikitext = $options['renderAsWikitext'] ?? false;
		$optimizeForSquare = $options['optimizeForSquare'] ?? false;
		$label = $options['label'] ?? '';

		if ( !isset( $options['fallback'] ) ) {
			if ( isset( $options['label'] ) ) {
				$options['fallback'] = $options['label'];
			} else {
				$options['fallback'] = 'none';
			}
		}

		// If image doesn't exist or is an icon, this will return false.
		$imageObj = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $image );

		// Use fallback icon or random icon if stated image doesn't exist
		if ( $image === null
			|| $image == ''
			|| ( $imageObj === false && !in_array( $image, $cannedIcons ) )
		) {
			if ( $options['fallback'] == 'none' ) {
				return '';
			} elseif ( in_array( $options['fallback'], $cannedIcons ) ) {
				$image = $options['fallback'];
			} else {
				$image = $cannedIcons[hexdec( sha1( $options['fallback'] )[0] )
					% count( $cannedIcons )];
			}
		}

		$imageCode = '';
		// Are we loading an image file or constructing a div based on an icon class?
		if ( $imageObj !== false ) {
			$squareAdjustmentAxis = null;
			if ( $optimizeForSquare || $label != '' ) {
				$fullHeight = $imageObj->getHeight();
				$fullWidth = $imageObj->getWidth();
				$ratio = $fullWidth / $fullHeight;  // get ratio of width to height
				if ( $ratio > 1 ) {
					$squareAdjustmentAxis = 'x';
				} elseif ( $ratio < 1 ) {
					$squareAdjustmentAxis = 'y';
				}
				// If image is a perfect square (ratio == 1) nothing needs to be done
			}
			$imageCode = self::makeImageFromFile( $imageObj, $width, $link,
				$renderAsWikitext, $label, $squareAdjustmentAxis );
		} elseif ( in_array( $image, $cannedIcons ) ) {
			$imageCode = self::makeImageFromIcon( $image, $width, $colour, $link,
				$renderAsWikitext, $label );
		}

		// Finishing up
		$wrapperAttributes = [ 'class' => $classes, 'style' => $css ];
		$imageBlock = Html::rawElement( 'div', $wrapperAttributes, $imageCode );
		return $imageBlock;
	}

	/**
	 * @param File $imageObj
	 * @param int $width
	 * @param string $link
	 * @param bool $renderAsWikitext
	 * @param string $label
	 * @param string|null $squareAdjustmentAxis x or y
	 * @return string
	 * @suppress SecurityCheck-DoubleEscaped Return value depends on $renderAsWikitext
	 */
	protected static function makeImageFromFile( $imageObj, $width, $link,
		$renderAsWikitext, $label, $squareAdjustmentAxis
	) {
		// This assumes that colours cannot be assigned to images.
		// This is currently true, but who knows what the future might hold!

		$parser = MediaWikiServices::getInstance()->getParser();

		$imageTitle = $imageObj->getTitle();
		$imageFullName = $imageTitle->getFullText();

		if ( $squareAdjustmentAxis == 'x' ) {
			$widthText = $width;
		} else {
			$widthText = 'x' . $width;  // i.e. "x64px"
		}

		$wikitext = "[[{$imageFullName}|{$widthText}px";

		if ( $link === false || $label != '' ) {
			$wikitext .= '|link=]]';
		} elseif ( is_string( $link ) ) {
			$wikitext .= "|link={$link}]]";
		} else {
			$wikitext .= ']]';
		}

		if ( $renderAsWikitext ) {
			if ( $squareAdjustmentAxis !== null ) {
				// We need another <div> wrapper to add the margin offsets
				// The main one is below

				$fullHeight = $imageObj->getHeight();
				$fullWidth = $imageObj->getWidth();
				$squareWrapperCss = '';

				if ( $squareAdjustmentAxis == 'y' ) {
					$adjustedWidth = ( $fullWidth * $width ) / $fullHeight;
					$offset = ceil( -1 * ( ( $adjustedWidth ) - $width ) / 2 );
				} elseif ( $squareAdjustmentAxis == 'x' ) {
					$adjustedHeight = ( $fullHeight * $width ) / $fullWidth;
					$offset = ceil( -1 * ( ( $adjustedHeight ) - $width ) / 2 );
					$squareWrapperCss = "margin-top:{$offset}px";
				}

				$wikitext = Html::rawElement(
					'div',
					[
						'class' => 'mw-ck-file-image-squareoptimized',
						'style' => $squareWrapperCss
					],
					$wikitext
				);
			}
			return $wikitext;
		} else {
			$imageHtml = $parser->parse( $wikitext, $imageTitle,
				ParserOptions::newFromAnon() )->getText();

			if ( $label != '' ) {
				$imageHtml = Html::rawElement(
					'div',
					[ 'class' => 'mw-ck-file-image' ],
					$imageHtml
				);
				if ( $link !== false ) {
					$imageHtml = self::linkFactory( $imageHtml, $link, $label,
						$imageObj
					);
				}
			}

			return $imageHtml;
		}
	}

	/**
	 * @param string $image
	 * @param int $width
	 * @param string $colour
	 * @param string $link
	 * @param bool $renderAsWikitext
	 * @param string $label
	 * @return string
	 */
	protected static function makeImageFromIcon( $image, $width, $colour, $link,
		$renderAsWikitext, $label
	) {
		// Rendering as wikitext with link is not an option here due to Tidy.

		$imageClasses = [ 'mw-ck-icon' ];
		if ( $colour != '' && $colour != 'black' ) {
			$imageClasses[] = 'mw-ck-icon-' . $image . '-' . $colour;
		} else {
			$imageClasses[] = 'mw-ck-icon-' . $image;
		}

		$imageHtml = Html::rawElement(
			'div',
			[
				'class' => $imageClasses,
				'style' => "width: {$width}px; height: {$width}px;"
			],
			''
		);

		if ( !$renderAsWikitext && $link !== false ) {
			$imageHtml = self::linkFactory( $imageHtml, $link, $label );
		}

		return $imageHtml;
	}

	/**
	 * @param string $imageHtml
	 * @param Title|string $link
	 * @param string $label
	 * @param null|File $imageObj
	 * @return string
	 */
	protected static function linkFactory( $imageHtml, $link, $label,
		$imageObj = null
	) {
		// Important assumption: image is being rendered as HTML and not wikitext.
		if ( $link instanceof Title ) {
			$linkHref = $link->getLinkURL();
		} elseif ( is_string( $link ) ) {
			$linkHref = Title::newFromText( $link )->getLinkURL();
		} elseif ( $imageObj !== null ) {
			$linkHref = $imageObj->getTitle()->getLinkURL();
		} else {
			$linkHref = '#';
		}

		if ( $label != '' ) {
			$imageHtml .= Html::rawElement(
				'span',
				[ 'class' => 'mw-ck-toc-item-label' ],
				$label
			);
		}
		return Html::rawElement( 'a', [ 'href' => $linkHref ], $imageHtml );
	}

	/**
	 * @return array All the canned icons in CollaborationKit
	 */
	public static function getCannedIcons() {
		// Keep this synced with the icons listed in the module in extension.json
		return [
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
