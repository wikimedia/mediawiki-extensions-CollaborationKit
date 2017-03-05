<?php

/**
 * A class to prepare a "human editable" serialization of data in CollaborationKit.
 * In the general case, blocks of data are separated by a fixed number of hyphens.
 * The blocks of text within do not contain keys, but the sequence of blocks is
 * defined by the content model.
 *
 * @file
 */

class CollaborationKitSerialization {

	// Main splitter
	const SERIALIZATION_SPLIT = "\n-----------------------\n";

	/**
	 * Prepares the "human editable" serialization
	 *
	 * @param array $content The contents to serialize, in sequential order
	 * @return string
	 */
	public static function getSerialization( $content ) {
		$retString = '';
		$numberOfItems = count( $content );
		for ( $i = 0; $i < $numberOfItems; $i++ ) {
			$retString .= $content[$i];
			// Don't add splitter after the last block
			if ( $i != $numberOfItems - 1 ) {
				$retString .= self::SERIALIZATION_SPLIT;
			}
		}
		return $retString;
	}
}
