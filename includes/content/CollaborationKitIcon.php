<?php

class CollaborationKitIcon {

	/**
	 * Generate an in icon based on an on-wiki file or a canned CK icon
	 * @param $icon string icon id, filename, or random seed
	 * @param $size int intended height/width for rendered icon in px
	 * @param $fallback string what to do for no icon; allowed values are 'random', 'none', or a valid icon id
	 * @return string html
	 */
	public static function makeIconOrImage( $icon, $size = 50, $colour = 'black', $fallback = 'random' ) {
		// We were going to only find files with file name extensions, but that's hard to parse, and there's no way to really handle ones that aren't uploaded, so we'll just look and see if they're uploaded and be done with it.
		if ( wfFindFile( $icon ) ) {
			// TODO also find if prefixed with 'file:', 'image:', etc
			return CollaborationKitIcon::makeImage( $icon, $size );
		} elseif ( $fallback == 'none' ) {
			return '';
		} else {
			// canned icons time

			$iconsPreset = CollaborationKitIcon::getCannedIcons();

			if ( !in_array( $icon, $iconsPreset ) && in_array( $fallback, $iconsPreset ) ) {
				return CollaborationKitIcon::makeIcon( $fallback, $size, 'lightgrey', '#eee' );
			} else {
				// makeicon falls back to making a random icon anyway, and we've ruled out all the other fallbacks at this point
				return CollaborationKitIcon::makeIcon( $icon, $size, $colour );
			}
		}
	}

	/**
	 * Generate an in icon using a canned CK icon
	 * @param $icon string icon id or random seed
	 * @param $size int intended height/width for rendered icon in px
	 * @param $fallback string what to do for no icon; allowed values are 'random', 'none', or a valid icon id
	 * @return string html
	 */
	public static function makeIcon( $icon, $size = 50, $colour, $background = 'transparent' ) {
		$iconsPreset = CollaborationKitIcon::getCannedIcons();

		if ( in_array( $icon, $iconsPreset ) ) {
			$iconClass = Sanitizer::escapeClass( $icon );
		} else {
			// Random time
			// Choose class name using $icon value as seed
			$iconClass = $iconsPreset[ hexdec( sha1( $icon )[0] ) % 27];
		}

		if ( !isset( $colour ) || $colour == 'black' ) {
			$colorSuffix = '';
		} else {
			$colorSuffix = '-' . $colour;
		}
		return Html::element(
			'div',
			[
				'class' => [
					'mw-ck-icon',
					'mw-ck-icon-' . $iconClass .  $colorSuffix
				],
				'css' => "height: {$size}px; width: {$size}px; background-color: $background;"
			]
		);
	}

	/**
	 * Make an image from a file onwiki
	 * Assumes the file exists, and this was already checked. Doesn't work if it doesn't.
	 * @param $file string filename
	 * @param $size int width, height in px
	 * @param $background string colour for optional css backgroundstuff
	 * @return string html
	 */
	public static function makeImage( $file, $size = 50, $background = 'transparent' ) {
		return Html::rawElement(
			'div',
			[
				'class' => 'mw-ck-file-image',
				'css' => "max-height: {$size}px; background-color: $background;"
			],
			wfFindFile( $file )->transform( [ 'width' => $size ] )->toHtml()
		);
	}

	/**
	 * @return array of stupidly many icons
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
