<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationListContentHandler
 */
class CollaborationListContentHandlerTest extends MediaWikiTestCase {

	/**
	 * @var CollaborationListContentHandler
	 */
	private $handler;

	public function setUp() {
		parent::setUp();

		$handler = new CollaborationListContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		static::assertTrue( $empty->isValid() );
	}

	public function testMakeMemberList() {
		$user = 'User:Willy on Wheels';
		$description = 'lol';
		$members = CollaborationListContentHandler::makeMemberList( $user, $description );
		static::assertTrue( $members->isValid() );
	}

}
