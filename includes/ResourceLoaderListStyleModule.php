<?php

class ResourceLoaderListStyleModule extends ResourceLoaderImageModule {
	protected function getCssDeclarations( $primary, $fallback ) {
		return [
			"list-style-image: /* @embed */ url( $fallback ) \9;",
			"list-style-image: /* @embed */ url( $primary );"
		];
	}
}
