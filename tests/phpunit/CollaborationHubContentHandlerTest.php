<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationHubContentHandler
 */
class CollaborationHubContentHandlerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var CollaborationHubContentHandler
	 */
	private $handler;

	public function setUp(): void {
		parent::setUp();

		$handler = new CollaborationHubContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		self::assertTrue( $empty->isValid() );
	}
}
