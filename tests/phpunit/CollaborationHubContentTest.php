<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationHubContent
 */
class CollaborationHubContentTest extends MediaWikiIntegrationTestCase {

	/** @var CollaborationHubContent */
	private $content;

	public function setUp(): void {
		$this->setMwGlobals( [
			'wgServer' => 'http://localhost',
			'wgScriptPath' => '/wiki',
			'wgScript' => '/wiki/index.php',
			'wgArticlePath' => '/wiki/index.php/$1',
			'wgActionPaths' => [],
		] );
		$content = new CollaborationHubContent(
			'{ "introduction": "Test content", "display_name": "foo",'
			. '"footer": "More test content", "colour": "violet", "content": ['
			. '{ "title": "Project:Wow", "image": "cool.png", "display_title": "Wow!" }'
			. '] }'
		);
		$this->content = TestingAccessWrapper::newFromObject( $content );
		parent::setUp();
	}

	/**
	 * Helper function to shorten lines
	 * @param string $text
	 * @return CollaborationHubContent
	 */
	private function m( $text ) {
		return new CollaborationHubContent( $text );
	}

	public function provideContentObjs() {
		return [
			[ $this->m(
				'{ "introduction": "\'\'Test\'\' content", "display_name": "foo",'
				. '"footer": "\'\'\'Test\'\'\' content footer", "colour": "violet", "content": ['
				. '{ "title": "Project:Wow", "image": "cool.png", "display_title": "Wow!" }'
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
		static::assertTrue( $content->isValid(), $id );
	}

	public function provideInvalid() {
		return [
			[ '{ afdsfda }' ],
			[ '{ "introduction": 1, "display_name": "", "content": "" }' ],
			[ '{ "display_name": [ "doggy" ], "content": "" }' ],
			[ '{ "display_name": "", "content": "" }' ],
			# FIXME Empty objects aren't being rejected like they should be.
			# [ '{ "display_name": "", "content": {} }' ],
			[ '{ "display_name": "", "content": [], "footer": [] }' ],
			[ '{ "display_name": "Legit", "content": { "title": "Project:Test/Test", "image": "bell", "display_title": "Test" } }' ]
		];
	}

	/**
	 * @dataProvider provideInvalid
	 */
	public function testIsNotValid( $contentText ) {
		$content = new CollaborationHubContent( $contentText );
		static::assertFalse( $content->isValid() );
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
		static::assertEquals( $expected[$id], $actual, $id );
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
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testContent( CollaborationHubContent $content, $id ) {
		$expected = [
			[ [ 'title' => 'Project:Wow', 'image' => 'cool.png', 'displayTitle' => 'Wow!' ] ],
			[],
			[],
		];
		$actual = $content->getContent();
		static::assertEquals( $expected[$id], $actual, $id );
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
		$actual = $wc->getParsedIntroduction( Title::newMainPage(), ParserOptions::newFromAnon() );
		static::assertEquals( $expected[$id], $actual, $id );
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
		$actual = $wc->getParsedFooter( Title::newMainPage(), ParserOptions::newFromAnon() );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedContent( CollaborationHubContent $content, $id ) {
		global $wgServer, $wgMetaNamespace;
		$expected = [
			"<div class=\"mw-ck-hub-section\" id=\"Wow!\"><div class=\"mw-ck-hub-section-header\"><h2><div class=\"mw-ck-section-image\" style=\"\"><a href=\"/wiki/index.php/Wow\"><div class=\"mw-ck-icon mw-ck-icon-heart\" style=\"width: 35px; height: 35px;\"></div></a></div><span class=\"mw-headline\">Wow!</span></h2></div><p class=\"mw-ck-hub-missingfeature-note\"><ext:ck:missingfeature-note target=\"$wgMetaNamespace:Wow\"/></p><ext:ck:editmarker page=\"$wgMetaNamespace:Wow\"target=\"$wgMetaNamespace:Wow\"message=\"collaborationkit-hub-missingpage-create\"link=\"$wgServer/wiki/index.php?title=Special:CreateHubFeature&collaborationhub=Main+Page&feature=Wow\"classes=\"\"icon=\"0\"framed=\"1\"primary=\"0\"/><span class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-buttonWidget'><a role='button' tabindex='0' href='$wgServer/wiki/index.php?title=Main_Page&amp;action=purge' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-iconElement-noIcon'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'collaborationkit-hub-missingpage-purgecache' )->text() . "</span><span class='oo-ui-indicatorElement-indicator oo-ui-indicatorElement-noIndicator'></span></a></span></div>",
			'',
			''
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedContent( Title::newMainPage(), ParserOptions::newFromAnon(), new ParserOutput );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetHubClasses( CollaborationHubContent $content, $id ) {
		$expected = [
			[ 'mw-ck-collaborationhub', 'mw-ck-list-square', 'mw-ck-theme-violet' ],
			[ 'mw-ck-collaborationhub', 'mw-ck-list-square', 'mw-ck-theme-lightgrey' ],
			[ 'mw-ck-collaborationhub', 'mw-ck-list-square', 'mw-ck-theme-lightgrey' ]
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getHubClasses();
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetMembersBlock( CollaborationHubContent $content, $id ) {
		$testMemberList = new CollaborationListContent( '{"columns":[{"items":[{"title":"User:X"}]}]}' );

		$block = "<h3>Meet our members!</h3><p><br />\n" .
			wfMessage( 'collaborationkit-list-isempty' )->text() .
			"\n</p><div class=\"mw-ck-members-buttons\"><span class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-buttonWidget'><a role='button' tabindex='0' href='/wiki/index.php/Main_Page/Members' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-iconElement-noIcon'></span><span class='oo-ui-labelElement-label'>" .
			wfMessage( 'collaborationkit-hub-members-view' )->text() .
			"</span><span class='oo-ui-indicatorElement-indicator oo-ui-indicatorElement-noIndicator'></span></a></span><ext:ck:editmarker page=\"Main Page/Members\"target=\"Main Page/Members\"message=\"collaborationkit-hub-members-signup\"link=\"/wiki/index.php?title=Main_Page/Members&action=edit\"classes=\"mw-ck-members-join\"icon=\"0\"framed=\"1\"primary=\"1\"/></div>";
		$expected = [ $block, $block, $block ];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getMembersBlock( Title::newMainPage(), ParserOptions::newFromAnon(), new ParserOutput, $testMemberList );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedAnnouncements( CollaborationHubContent $content, $id ) {
		$testAnnouncement = '* The cafeteria is out of empanadas. We apologize for the inconvenience.';

		$block = '<h3>' . wfMessage( 'collaborationkit-hub-pagetitle-announcements' )->text() .
			'<ext:ck:editmarker page="Main Page/Announcements"target="Main Page/Announcements"message="edit"link="/wiki/index.php?title=Main_Page/Announcements&action=edit"classes="mw-ck-hub-section-button mw-editsection-like"icon="edit"framed="0"primary="0"/>' .
			'</h3>* The cafeteria is out of empanadas. We apologize for the inconvenience.';
		$expected = [ $block, $block, $block ];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedAnnouncements( Title::newMainPage(), ParserOptions::newFromAnon(), $testAnnouncement );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetSecondFooter( CollaborationHubContent $content, $id ) {
		global $wgServer;
		$block = '<ext:ck:editmarker page="Main Page"target="Main Page"message="collaborationkit-hub-manage"link="/wiki/index.php?title=Main_Page&action=edit"classes=""icon="edit"framed="1"primary="0"/><ext:ck:editmarker page="Main Page"target="Main Page/SUPERSECRETDUMMYSUBPAGEISUREHOPEDOESNTACTUALLYEXIST!"message="collaborationkit-hub-addpage"link="' . $wgServer . '/wiki/index.php?title=Special:CreateHubFeature&collaborationhub=Main+Page"classes=""icon="add"framed="1"primary="0"/>';
		$expected = [ $block, $block, $block ];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getSecondFooter( Title::newMainPage() );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testMakeHeader1( CollaborationHubContent $content, $id ) {
		$testContentArray = [
			'title' => 'Amazing Worklist',
			'image' => 'pageribbon'
		];
		$expected = [
			'<div class="mw-ck-hub-section" id="Amazing_Worklist"><div class="mw-ck-hub-section-header"><h2><div class="mw-ck-section-image" style=""><a href="/wiki/index.php/Amazing_Worklist"><div class="mw-ck-icon mw-ck-icon-pageribbon" style="width: 35px; height: 35px;"></div></a></div><span class="mw-headline">Amazing Worklist</span></h2></div>',
			'<div class="mw-ck-hub-section" id="Amazing_Worklist1"><div class="mw-ck-hub-section-header"><h2><div class="mw-ck-section-image" style=""><a href="/wiki/index.php/Amazing_Worklist"><div class="mw-ck-icon mw-ck-icon-pageribbon" style="width: 35px; height: 35px;"></div></a></div><span class="mw-headline">Amazing Worklist</span></h2></div>',
			'<div class="mw-ck-hub-section" id="Amazing_Worklist2"><div class="mw-ck-hub-section-header"><h2><div class="mw-ck-section-image" style=""><a href="/wiki/index.php/Amazing_Worklist"><div class="mw-ck-icon mw-ck-icon-pageribbon" style="width: 35px; height: 35px;"></div></a></div><span class="mw-headline">Amazing Worklist</span></h2></div>'
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->makeHeader( Title::newMainPage(), $testContentArray );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testMakeHeader2( CollaborationHubContent $content, $id ) {
		$testContentArray = [
			'title' => 'Gonzo Worklist',
			'image' => 'pageribbon',
			'displayTitle' => 'You Best Believe It'
		];
		$expected = [
			'<div class="mw-ck-hub-section" id="You_Best_Believe_It"><div class="mw-ck-hub-section-header"><h2><div class="mw-ck-section-image" style=""><a href="/wiki/index.php/Gonzo_Worklist"><div class="mw-ck-icon mw-ck-icon-pageribbon" style="width: 35px; height: 35px;"></div></a></div><span class="mw-headline">You Best Believe It</span></h2></div>',
			'<div class="mw-ck-hub-section" id="You_Best_Believe_It1"><div class="mw-ck-hub-section-header"><h2><div class="mw-ck-section-image" style=""><a href="/wiki/index.php/Gonzo_Worklist"><div class="mw-ck-icon mw-ck-icon-pageribbon" style="width: 35px; height: 35px;"></div></a></div><span class="mw-headline">You Best Believe It</span></h2></div>',
			'<div class="mw-ck-hub-section" id="You_Best_Believe_It2"><div class="mw-ck-hub-section-header"><h2><div class="mw-ck-section-image" style=""><a href="/wiki/index.php/Gonzo_Worklist"><div class="mw-ck-icon mw-ck-icon-pageribbon" style="width: 35px; height: 35px;"></div></a></div><span class="mw-headline">You Best Believe It</span></h2></div>'
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->makeHeader( Title::newMainPage(), $testContentArray );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToHumanEditable( CollaborationHubContent $content, $id ) {
		$spl = CollaborationKitSerialization::SERIALIZATION_SPLIT;
		$expected = [
			"foo" . $spl . "''Test'' content" . $spl . "'''Test''' content footer" . $spl . "none" . $spl . "violet" . $spl . "Project:Wow|image=cool.png|display_title=Wow!\n",
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl,
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->convertToHumanEditable();
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertFromHumanEditable( CollaborationHubContent $content, $id ) {
		$spl = CollaborationKitSerialization::SERIALIZATION_SPLIT;
		$expected = [
			FormatJson::encode( [
				'display_name' => "foo",
				'introduction' => "''Test'' content",
				'footer' => "'''Test''' content footer",
				'image' => 'none',
				'colour' => 'violet',
				'content' => [ [
					'title' => 'Project:Wow',
					'image' => 'cool.png',
					'display_title' => 'Wow!'
				] ]
			], true, FormatJson::ALL_OK ),
			FormatJson::encode( [ 'display_name' => '', 'introduction' => '', 'footer' => '', 'image' => 'none', 'colour' => 'lightgrey', 'content' => [] ], true, FormatJson::ALL_OK ),
			FormatJson::encode( [ 'display_name' => '', 'introduction' => '', 'footer' => '', 'image' => 'none', 'colour' => 'lightgrey', 'content' => [] ], true, FormatJson::ALL_OK )
		];
		$testCases = [
			"foo" . $spl . "''Test'' content" . $spl . "'''Test''' content footer" . $spl . "none" . $spl . "violet" . $spl . "Project:Wow|image=cool.png|display_title=Wow!",
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl,
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = FormatJson::encode( $wc->convertFromHumanEditable( $testCases[ $id ] ), true, FormatJson::ALL_OK );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testTextNativeDataEquivalent( CollaborationHubContent $content, $id ) {
		if ( !method_exists( $content, 'getNativeData' ) ) {
			static::markTestSkipped( 'getNativeData() no longer present. Skipping comparison.' );
		}
		static::assertEquals( $content->getNativeData(),  $content->getText(),
			'Call to NativeData() does not match call to getText()' );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToJson( CollaborationHubContent $content, $id ) {
		$json = $content->convert( CONTENT_MODEL_JSON );

		static::assertInstanceOf( JsonContent::class, $json );
		static::assertTrue( $json->IsValid() );
	}
}
