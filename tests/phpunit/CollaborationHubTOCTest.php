<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationHubTOC
 */
class CollaborationHubTOCTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var CollaborationHubTOC
	 */
	private $content;

	public function setUp(): void {
		parent::setUp();
		$content = new CollaborationHubTOC();
		$this->content = TestingAccessWrapper::newFromObject( $content );
	}

	public function testRenderToC() {
		static::markTestIncomplete(); // pending fix to issues regarding images and tests

		$wc = $this->content;
		$expected = '<div class="mw-ck-toc-container"><div class="mw-ck-toc-label">Project features</div><ul><li class="mw-ck-toc-item"><div style=""><a href="#A"><div class="mw-ck-icon mw-ck-icon-edit" style="width: 50px; height: 50px;"></div><span class="mw-ck-toc-item-label">A</span></a></div></li><li class="mw-ck-toc-item"><div style=""><a href="#B"><div class="mw-ck-icon mw-ck-icon-key" style="width: 50px; height: 50px;"></div><span class="mw-ck-toc-item-label">B</span></a></div></li><li class="mw-ck-toc-item"><div style=""><a href="#C"><div class="mw-ck-file-image" style="width:50px; max-height:50px; overflow:hidden;"><img alt="" src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b9/Samoyed.jpg/50px-Samoyed.jpg" width="50" height="56" /></div><span class="mw-ck-toc-item-label">C</span></a></div></li></ul></div>';
		$actual = $wc->renderToC( [ [ 'title' => 'A' ], [ 'title' => 'B', 'image' => 'key' ], [ 'title' => 'C', 'image' => 'Samoyed.jpg' ] ] );
		static::assertEquals( $expected, $actual, 0 );
	}

	// TODO find a way to test renderSubpageToC
	// The way it works right now, it takes a Title, slurps out the latest Revision, and gets the ToC that way
	// That's harder to test with.
}
