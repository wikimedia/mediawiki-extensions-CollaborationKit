<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationKitImage
 */
class CollaborationKitImageTest extends MediaWikiIntegrationTestCase {

	/** @var CollaborationKitImage */
	private $str;

	public function setUp(): void {
		parent::setUp();
		$content = new CollaborationKitImage();
		$this->str = TestingAccessWrapper::newFromObject( $content );
	}

	public function provideTestConfigs() {
		return [
			[ 'key', [ 'renderAsWikitext' => true, 'classes' => [ 'test1', 'test2' ] ], 0 ],
			[ 'key', [ 'renderAsWikitext' => true, 'colour' => 'violet' ], 1 ],
			[ 'key', [ 'renderAsWikitext' => true, 'css' => 'display:none' ], 2 ],
			[ 'key', [ 'renderAsWikitext' => false, 'classes' => [ 'test1', 'test2' ] ], 3 ],
			[ 'key', [ 'renderAsWikitext' => false, 'link' => 'Delightful' ], 4 ],
			[ 'key', [ 'renderAsWikitext' => false, 'colour' => 'violet' ], 5 ],
			[ 'key', [ 'renderAsWikitext' => false, 'css' => 'display:none' ], 6 ],
			[ 'key', [ 'renderAsWikitext' => false, 'label' => 'WikiWiki' ], 7 ],
			[ 'Example.svg', [ 'testImage' => 'File:Example.svg', 'renderAsWikitext' => true, 'classes' => [ 'test1', 'test2' ] ], 8 ],
			[ 'Example.svg', [ 'testImage' => 'File:Example.svg', 'renderAsWikitext' => true, 'css' => 'display:none' ], 9 ],
			[ '', [ 'renderAsWikitext' => true, 'fallback' => 'key' ], 10 ],
			[ 'Example.svg', [ 'testImage' => 'File:Example.svg', 'renderAsWikitext' => false, 'classes' => [ 'test1', 'test2' ] ], 11 ],
			[ 'Example.svg', [ 'testImage' => 'File:Example.svg', 'renderAsWikitext' => false, 'link' => 'Delightful' ], 12 ],
			[ 'Example.svg', [ 'testImage' => 'File:Example.svg', 'renderAsWikitext' => false, 'css' => 'display:none' ], 13 ],
			[ 'Example.svg', [ 'testImage' => 'File:Example.svg', 'renderAsWikitext' => false, 'label' => 'WikiWiki' ], 14 ],
			[ '', [ 'renderAsWikitext' => false, 'fallback' => 'key' ], 15 ]
		];
	}

	/**
	 * @dataProvider provideTestConfigs
	 */
	public function testMakeImage( $testImage, $testConfig, $id ) {
		static::markTestIncomplete(); // pending fix to issues regarding images and tests

		$expected = [
			'<div class="test1 test2" style=""><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></div>',
			'<div style=""><div class="mw-ck-icon mw-ck-icon-key-violet" style="width: 76px; height: 76px;"></div></div>',
			'<div style="display:none;"><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></div>',
			'<div class="test1 test2" style=""><a href="#"><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></a></div>',
			'<div style=""><a href="/wiki/index.php/Delightful"><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></a></div>',
			'<div style=""><a href="#"><div class="mw-ck-icon mw-ck-icon-key-violet" style="width: 76px; height: 76px;"></div></a></div>',
			'<div style="display:none;"><a href="#"><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></a></div>',
			'<div style=""><a href="#"><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div><span class="mw-ck-toc-item-label">WikiWiki</span></a></div>',
			'<div class="test1 test2" style="">[[File:Example.svg|76px]]</div>',
			'<div style="display:none;">[[File:Example.svg|76px]]</div>',
			'<div style=""><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></div>',
			'<div class="test1 test2" style=""><a href="/wiki/index.php/File:Example.svg"><img alt="" src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/84/Example.svg/76px-Example.svg.png" width="76" height="76" /></a></div>',
			'<div style=""><a href="/wiki/index.php/Delightful"><img alt="" src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/84/Example.svg/76px-Example.svg.png" width="76" height="76" /></a></div>',
			'<div style="display:none;"><a href="/wiki/index.php/File:Example.svg"><img alt="" src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/84/Example.svg/76px-Example.svg.png" width="76" height="76" /></a></div>',
			'<div style=""><a href="/wiki/index.php/File:Example.svg"><div class="mw-ck-file-image" style="width:76px; max-height:76px; overflow:hidden;"><img alt="" src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/84/Example.svg/76px-Example.svg.png" width="76" height="76" /></div><span class="mw-ck-toc-item-label">WikiWiki</span></a></div>',
			'<div style=""><a href="#"><div class="mw-ck-icon mw-ck-icon-key" style="width: 76px; height: 76px;"></div></a></div>',
		];

		$actual = CollaborationKitImage::makeImage( $testImage, 76, $testConfig );
		static::assertEquals( $expected[$id], $actual, $id );
	}

}
