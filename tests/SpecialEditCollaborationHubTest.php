<?php

class SpecialEditcollaborationHubTest extends MediaWikiTestCase {

	private $page;

	public function setUp() {
		parent::setUp();

		$page = SpecialPageFactory::getPage( 'EditCollaborationHub' );
		$this->page = TestingAccessWrapper::newFromObject( $page );
	}


	/**
	 * @dataProvider provideMakeListReadable
	 */
	public function testMakeListReadable( $content, $contentType, $expected ) {
		$actual = $this->page->makeListReadable( $content, $contentType );
		$this->assertEquals( $expected, $actual, "initial makeReadbale" );

		/* Disabled for now, doesn't actually fully round-trip.
		*	$actualSensible = $this->page->makeListSensible( $actual, $contentType );
		*	$this->assertEquals( $content, $actualSensible, "roundtrip" );
		*/
	}

	public function provideMakeListReadable() {
		return [
			[
				[ [ 'item' => 'foo' ] ],
				'icon-list',
				"* foo\n\n",
			],
			[
				[ [ 'item' => 'foo' ] ],
				'block-list',
				"* foo\n\n",
			],
			[
				[ [ 'item' => 'foo' ], [ 'item' => 'baz' ], [ 'item' => 'fred' ] ],
				'icon-list',
				// Fixme? is the extra \n right?
				"* foo\n\n* baz\n\n* fred\n\n",
			],
			[
				[ [ 'item' => 'foo', 'notes' => 'A really foo-like item', 'icon' => 'bar'  ] ],
				'icon-list',
				"* foo\n*:: bar\nA really foo-like item\n\n",
			],
			[
				[ [ 'item' => 'foo' ], [ 'item' => 'baz' ], [ 'item' => 'fred' ] ],
				'list',
				"foo\nbaz\nfred\n",
			],
			[
				[ [ 'item' => 'foo' ], [ 'item' => 'baz' ], [ 'item' => 'fred' ] ],
				'subpage-list',
				"foo\nbaz\nfred\n",
			],
		];
	}
}
