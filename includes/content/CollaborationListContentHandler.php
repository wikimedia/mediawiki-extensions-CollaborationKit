<?php

// We extend TextContentHandler instead of JsonContentHandler since
// we do not display this as Json code.
class CollaborationListContentHandler extends TextContentHandler {

	public function __construct( $modelId = 'CollaborationListContent' ) {
		// FIXME, second argument should be [ CONTENT_FORMAT_JSON ],
		// but I don't understand enough about content handler to figure
		// out what that actually does...
		parent::__construct( $modelId );
	}

	/**
	 * @param string $text
	 * @param string $format
	 * @return CollaborationListContent
	 * @throws MWContentSerializationException
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		$content = new CollaborationListContent( $text );
		if ( !$content->isValid() ) {
			throw new MWContentSerializationException( 'The collaborationlist content is invalid.' );
		}
		return $content;
	}

	/**
	 * @return CollaborationListContent
	 */
	public function makeEmptyContent() {
		$empty = <<<JSON
			{
				"items": [],
				"options": {},
				"description": ""
			}
JSON;
		return new CollaborationListContent( $empty );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'CollaborationListContent';
	}

	/**
	 * @return string
	 */
	/*protected function getDiffEngineClass() {
		return 'CollaborationListDiffEngine';
	}*/

	/**
	 * FIXME is this really true?
	 * @return bool
	 */
	public function isParserCacheSupported() {
		return true;
	}

/**** This disables Special:ChangeContentModel
	public function supportsDirectEditing() {
		return false;
	}
*/

	public function supportsDirectApiEditing() {
		return true;
	}
}


