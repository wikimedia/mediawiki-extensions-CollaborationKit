<?php

class CollaborationHubContentHandlerTest extends MediaWikiTestCase {

	/**
	 * @var CollaborationHubContentHandler
	 */
	private $handler;

	public function setUp() {
		parent::setUp();

		$handler = new CollaborationHubContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		self::assertTrue( $empty->isValid() );
	}
}
