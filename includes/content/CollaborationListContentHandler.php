<?php

// We extend TextContentHandler instead of JsonContentHandler since
// we do not display this as Json code.
class CollaborationListContentHandler extends TextContentHandler {

	const FORMAT_WIKI = 'text/x-collabkit';

	public function __construct(
		$modelId = 'CollaborationListContent',
		$formats = [ CONTENT_FORMAT_JSON, CONTENT_FORMAT_TEXT, self::FORMAT_WIKI ]
	) {
		// text/x-collabkit is a format for lists similar to <gallery>.
		// CONTENT_FORMAT_TEXT is for back-compat with old revs. Could be removed.

		// @todo Ideally, we'd have the preferred format for editing be self::FORMAT_WIKI
		// and the preferred format for db be CONTENT_FORMAT_JSON. Unclear if that's
		// possible.
		parent::__construct( $modelId, $formats );
	}

	/**
	 * @param string $text
	 * @param string $format
	 * @return CollaborationListContent
	 * @throws MWContentSerializationException
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		if ( $format === self::FORMAT_WIKI ) {
			$data = CollaborationListContent::convertFromHumanEditable( $text );
			$text = FormatJson::encode( $data );
		}
		$content = new CollaborationListContent( $text );
		if ( !$content->isValid() ) {
			throw new MWContentSerializationException( 'The collaborationlist content is invalid.' );
		}
		return $content;
	}

	public function serializeContent( Content $content, $format = null ) {
		if ( $format === self::FORMAT_WIKI ) {
			return $content->convertToHumanEditable();
		}
		return parent::serializeContent( $content, $format );
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


