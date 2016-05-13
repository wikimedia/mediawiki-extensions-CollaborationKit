<?php

class CollaborationHubContentTest extends MediaWikiTestCase {

	private $content;

	public function setUp() {
		parent::setUp();
		$content = new CollaborationHubContent(
			'{ "description": "Test content", "page_name": "foo",'
			. '"page_type": "userlist", "content": {'
			. '"type": "icon-list", "items": ['
			. '{"item": "Me!", "icon": "cool.png", "notes": "not you" }'
			. ']}}'
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
				'{ "description": "\'\'Test\'\' content", "page_name": "foo",'
				. '"page_type": "userlist", "content": {'
				. '"type": "icon-list", "items": ['
				. '{"item": "Me!", "icon": "cool.png", "notes": "not you" }'
				. ']}}'
			), 0 ],
			[ ( new CollaborationHubContentHandler )->makeEmptyContent(), 1 ],
			[ $this->m( '{ "page_name": "", "content": { "type": "subpage-list", "items": []}}' ), 2 ],
			[ $this->m( '{ "page_name": "", "content": { "type": "icon-list", "items": []}}' ), 3 ],
			[ $this->m( '{ "page_name": "", "page_type": "default", "content": { "type": "block-list", "items": []}}' ), 4 ],
			[ $this->m( '{ "page_name": "", "page_type": "main", "content": { "type": "list", "items": []}}' ), 5 ],
			[ $this->m( '{ "page_name": "", "content": "Wikitext [[here]]!"}' ), 6 ],
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
			[ '{afdsfda}' ],
			[ '{ "description": 1, "page_name": "", "content": ""}' ],
			[ '{ "page_name": [ "doggy"], "content": "" }' ],
			[ '{ "page_type": "food", "page_name": "", "content": ""}' ],
			[ '{ "page_type": {}, "page_name": "", "content": ""}' ],
			[ '{ "page_name": "", "description": "", "page_type": "default"}' ],
			[ '{ "page_name": "", "description": "", "page_type": "default", "content":5}' ],
			[ '{ "page_name": "", "content": {}}' ],
			[ '{ "page_name": "", "content": { "type": "invalid", "items": []}}' ],
			[ '{ "page_name": "", "content": { "type": "list", "items": [ { "notes": "cat" }]}}' ],
			[ '{ "page_name": "", "content": { "type": "list", "items": [ { "icon": "cat" }]}}' ],
			[ '{ "page_name": "", "content": { "type": "list", "items": [ { "item": [] }]}}' ],
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
	public function testGetDescription( CollaborationHubContent $content, $id ) {
		$expected = [
			"''Test'' content",
			'',
			'',
			'',
			'',
			'',
			'',
		];
		$actual = $content->getDescription();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testContent( CollaborationHubContent $content, $id ) {
		$expected = [
			[ [ "item" => "Me!", "icon" => "cool.png", "notes" => "not you" ] ],
			'',
			[],
			[],
			[],
			[],
			'Wikitext [[here]]!',
		];
		$actual = $content->getContent();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetPageType( CollaborationHubContent $content, $id ) {
		$expected = [
			'userlist',
			'default',
			'default',
			'default',
			'default',
			'main',
			'default',
		];
		$actual = $content->getPageType();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetContentType( CollaborationHubContent $content, $id ) {
		$expected = [
			'icon-list',
			'wikitext',
			'subpage-list',
			'icon-list',
			'block-list',
			'list',
			'wikitext',
		];
		$actual = $content->getContentType();
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetPossibleTypes( CollaborationHubContent $content, $id ) {
		$expected = [
			'wikitext',
			'subpage-list',
			'icon-list',
			'block-list',
			'list'
		];
		$actual = $content->getPossibleTypes();
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedDescription( CollaborationHubContent $content, $id ) {
		$expected = [
			"<p><i>Test</i> content\n</p>",
			'',
			'',
			'',
			'',
			'',
			'',
			'',
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedDescription( Title::newMainPage(), new ParserOptions );
		$this->assertEquals( $expected[$id], $actual, $id );
	}

	public function testGetParsedContent() {
		$this->markTestIncomplete();
		// FIXME implement.
		// getParsedContent does not appear to be entirely stable yet.
	}

	public function testGenerateList() {
		$this->markTestIncomplete();
		// FIXME implement.
		// generateList() does not appear to be entirely stable yet.
	}

	public function testFillParserOutput() {
		$this->markTestIncomplete();
		// FIXME implement.
		// fillParserOutput does not appear to be entirely stable yet.
	}
}
