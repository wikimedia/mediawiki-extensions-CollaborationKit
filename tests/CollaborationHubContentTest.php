<?php

class CollaborationHubContentTest extends MediaWikiTestCase {

	private $content;

	public function setUp() {
		parent::setUp();
		$content = new CollaborationHubContent(
			'{ "introduction": "Test content", "display_name": "foo",'
			. '"footer": "More test content", "content": ['
			. '{ "title": "Me!", "image": "cool.png" }'
			. '] }'
		);
		$this->content = TestingAccessWrapper::newFromObject( $content );
	}

	/**
	 * Helper function to shorten lines
	 */
	private function m( $text ) {
		return new CollaborationHubContent( $text );
	}

	public function provideContentObjs() {
		return [
			[ $this->m(
				'{ "introduction": "\'\'Test\'\' content", "display_name": "foo",'
				. '"footer": "\'\'\'Test\'\'\' content footer", "content": ['
				. '{ "title": "Me!", "image": "cool.png" }'
				. '] }'
			), 0 ],
			[ ( new CollaborationHubContentHandler )->makeEmptyContent(), 1 ],
			[ $this->m( '{ "display_name": "", "content": [] }' ), 2 ]
		];
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testIsValid( CollaborationHubContent $content, $id ) {
		$this->assertTrue( $content->isValid(), $id );
	}

	public function provideInvalid() {
		return [
			[ '{ afdsfda }' ],
			[ '{ "introduction": 1, "display_name": "", "content": "" }' ],
			[ '{ "display_name": [ "doggy" ], "content": "" }' ],
			[ '{ "page_type": "food", "display_name": "", "content": "" }' ],
			[ '{ "page_type": {}, "display_name": "", "content": "" }' ],
			[ '{ "display_name": "", "content": {} }' ],
			[ '{ "display_name": "", "content": [], "footer": [] }' ],
		];
	}

	/**
	 * @dataProvider provideInvalid
	 */
	public function testIsNotValid( $contentText ) {
		$content = new CollaborationHubContent( $contentText );
		$this->assertFalse( $content->isValid() );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetIntroduction( CollaborationHubContent $content, $id ) {
		$expected = [
			"''Test'' content",
			'',
			'',
		];
		$actual = $content->getIntroduction();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetFooter( CollaborationHubContent $content, $id ) {
		$expected = [
			"'''Test''' content footer",
			'',
			'',
		];
		$actual = $content->getFooter();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testContent( CollaborationHubContent $content, $id ) {
		$expected = [
			[ [ "title" => "Me!", "image" => "cool.png", "displayTitle" => null ] ],
			[],
			[],
		];
		$actual = $content->getContent();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedIntroduction( CollaborationHubContent $content, $id ) {
		$expected = [
			"<p><i>Test</i> content\n</p>",
			'',
			'',
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedIntroduction( Title::newMainPage(), new ParserOptions );
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedFooter( CollaborationHubContent $content, $id ) {
		$expected = [
			"<p><b>Test</b> content footer\n</p>",
			'',
			'',
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedFooter( Title::newMainPage(), new ParserOptions );
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	public function testGetParsedContent() {
		$this->markTestIncomplete();
		// FIXME implement.
		// getParsedContent does not appear to be entirely stable yet.
	}

	public function testFillParserOutput() {
		$this->markTestIncomplete();
		// FIXME implement.
		// fillParserOutput does not appear to be entirely stable yet.
	}
}
