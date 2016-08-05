<?php

class CollaborationHubContentHandlerTest extends MediaWikiTestCase {

	private $handler;

	public function setUp() {
		parent::setUp();

		$handler = new CollaborationHubContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	/**
	 * @expectedException MWContentSerializationException
	 */
	public function testUnserializeContent() {
		$this->handler->unserializeContent( 'There once was a horse named bob.' );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		$this->assertTrue( $empty->isValid() );
	}
}
