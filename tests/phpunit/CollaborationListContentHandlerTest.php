<?php

class CollaborationListContentHandlerTest extends MediaWikiTestCase {

	private $handler;

	public function setUp() {
		parent::setUp();

		$handler = new CollaborationListContentHandler;
		$this->handler = TestingAccessWrapper::newFromObject( $handler );
	}

	public function testMakeEmptyContent() {
		$empty = $this->handler->makeEmptyContent();
		$this->assertTrue( $empty->isValid() );
	}

	public function testMakeMemberList() {
		$user = "User:Willy on Wheels";
		$description = "lol";
		$members = $this->handler->makeMemberList( $user, $description );
		$this->assertTrue( $members->isValid() );
	}

}
