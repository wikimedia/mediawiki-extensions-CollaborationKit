<?php

class CollaborationHubDiffEngine extends DifferenceEngine {

	/**
	 * Implement our own diff rendering.
	 * @param $old Content Old content
	 * @param $new Content New content
	 *
	 * @throws Exception If old or new content is not an instance of CollaborationHubContent
	 * @return bool|string
	 */
	public function generateContentDiffBody( Content $old, Content $new ) {
		if ( !( $old instanceof CollaborationHubContent )
			|| !( $new instanceof CollaborationHubContent )
		) {
			throw new Exception( 'CollaborationKit cannot diff content types other than CollaborationHubContent' );
		}

		$output = '';

		// PANIC
		// TODO Implement this

		return $output;
	}
}
