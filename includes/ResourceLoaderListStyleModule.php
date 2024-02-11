<?php

/**
 * Class for loading ResourceLoader modules
 *
 * @file
 */

use MediaWiki\ResourceLoader\ImageModule;

class ResourceLoaderListStyleModule extends ImageModule {
	/** @inheritDoc */
	protected function getCssDeclarations( $primary, $fallback = null ): array {
		return [
			"list-style-image: /* @embed */ url( $primary );"
		];
	}
}
