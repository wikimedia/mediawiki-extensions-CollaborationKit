<?php

/**
 * Class for loading ResourceLoader modules
 *
 * @file
 */

class ResourceLoaderListStyleModule extends ResourceLoaderImageModule {
	/** @inheritDoc */
	protected function getCssDeclarations( $primary, $fallback ): array {
		return [
			"list-style-image: /* @embed */ url( $fallback ) \9;",
			"list-style-image: /* @embed */ url( $primary );"
		];
	}
}
