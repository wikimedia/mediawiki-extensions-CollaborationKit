<?php

use MediaWiki\Page\PageReferenceValue;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationListContentHandler
 */
class CollaborationListContentHandlerTest extends MediaWikiIntegrationTestCase {
	use CollaborationListTrait;

	/**
	 * @var CollaborationListContentHandler
	 */
	private $handler;

	public function setUp(): void {
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

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testPreSaveTransform( CollaborationListContent $content, $id ) {
		$contentTransformer = $this->getServiceContainer()->getContentTransformer();
		$user = UserIdentityValue::newAnonymous( '123.123.123.123' );
		$output = $contentTransformer->preSaveTransform(
			$content,
			PageReferenceValue::localReference( NS_MAIN, 'Test.pdf' ),
			$user,
			ParserOptions::newFromUser( $user )
		);

		static::assertInstanceOf( CollaborationListContent::class, $output );
		static::assertFalse( $content === $output,
			'Method should have returned new object with formatted JSON' );
	}

	public function testPreSaveTransformInvalidJSON() {
		$content = new CollaborationListContent( 'NOT JSON' );
		$contentTransformer = $this->getServiceContainer()->getContentTransformer();
		$user = UserIdentityValue::newAnonymous( '123.123.123.123' );
		$output = $contentTransformer->preSaveTransform(
			$content,
			PageReferenceValue::localReference( NS_MAIN, 'Test.pdf' ),
			$user,
			ParserOptions::newFromUser( $user )
		);

		static::assertInstanceOf( CollaborationListContent::class, $output );
		static::assertTrue( $content === $output,
			'Method should have returned object itself due to invalid content' );
	}
}
