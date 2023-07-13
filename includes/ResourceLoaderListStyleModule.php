<?php

/**
 * Class for loading ResourceLoader modules
 *
 * @file
 */

class ResourceLoaderListStyleModule extends ResourceLoaderImageModule {
	/** @inheritDoc */
	protected function getCssDeclarations( $primary, $fallback = null ): array {
		return [
			"list-style-image: /* @embed */ url( $primary );"
		];
	}
}
