<?php

class CollaborationHubContentHandlerTest extends MediaWikiTestCase {

	private $handler;

	public function setUp() {
		parent::setUp();

		$handler = new CollaborationHubContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		$this->assertTrue( $empty->isValid() );
	}
}
