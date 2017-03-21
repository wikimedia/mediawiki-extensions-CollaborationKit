<?php

class CollaborationHubContentTest extends MediaWikiTestCase {

	private $content;

	public function setUp() {
		$this->setMwGlobals( [
			'wgServer' => 'http://localhost',
			'wgScriptPath' => '/wiki',
			'wgScript' => '/wiki/index.php',
			'wgArticlePath' => '/wiki/index.php/$1',
			'wgActionPaths' => [],
		] );
		$content = new CollaborationHubContent(
			'{ "introduction": "Test content", "display_name": "foo",'
			. '"footer": "More test content", "colour": "khaki", "content": ['
			. '{ "title": "Project:Wow", "image": "cool.png", "display_title": "Wow!" }'
			. '] }'
		);
		$this->content = TestingAccessWrapper::newFromObject( $content );
		parent::setUp();
	}

	/**
	 * Helper function to shorten lines
	 * @param $text string
	 * @return CollaborationHubContent
	 */
	private function m( $text ) {
		return new CollaborationHubContent( $text );
	}

	public function provideContentObjs() {
		return [
			[ $this->m(
				'{ "introduction": "\'\'Test\'\' content", "display_name": "foo",'
				. '"footer": "\'\'\'Test\'\'\' content footer", "colour": "khaki", "content": ['
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
		static::assertEquals( $expected[ $id ], $actual, $id );
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
		static::assertEquals( $expected[ $id ], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testContent( CollaborationHubContent $content, $id ) {
		$expected = [
			[ [ "title" => "Project:Wow", "image" => "cool.png", "displayTitle" => 'Wow!' ] ],
			[],
			[],
		];
		$actual = $content->getContent();
		static::assertEquals( $expected[ $id ], $actual, $id );
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
		static::assertEquals( $expected[ $id ], $actual, $id );
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
		static::assertEquals( $expected[ $id ], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedContent( CollaborationHubContent $content, $id ) {
		global $wgServer;
		$expected = [
			"<div class=\"mw-ck-hub-section\" id=\"Wow.21\"><h2><span class=\"mw-headline\">Wow!</span></h2><p class=\"mw-ck-hub-missingfeature-note\">" . wfMessage( 'collaborationkit-hub-missingpage-note' ) . "</p><span aria-disabled='false' class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-flaggedElement-progressive oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='" . $wgServer . "/wiki/index.php?title=Special:CreateHubFeature&amp;collaborationhub=Main+Page&amp;feature=Wow' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-image-progressive'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'collaborationkit-hub-missingpage-create' ) . "</span><span class='oo-ui-indicatorElement-indicator oo-ui-image-progressive'></span></a></span><span aria-disabled='false' class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='" . $wgServer . "/wiki/index.php?title=Main_Page&amp;action=purge' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'collaborationkit-hub-missingpage-purgecache' ) . "</span><span class='oo-ui-indicatorElement-indicator'></span></a></span></div>",
			'',
			''
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedContent( Title::newMainPage(), new ParserOptions, new ParserOutput );
		static::assertEquals( $expected[ $id ], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetHubClasses( CollaborationHubContent $content, $id ) {
		$expected = [
			[ 'mw-ck-collaborationhub', 'mw-ck-list-square', 'mw-ck-theme-khaki' ],
			[ 'mw-ck-collaborationhub', 'mw-ck-list-square', 'mw-ck-theme-lightgrey' ],
			[ 'mw-ck-collaborationhub', 'mw-ck-list-square', 'mw-ck-theme-lightgrey' ]
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getHubClasses();
		static::assertEquals( $expected[ $id ], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetMembersBlock( CollaborationHubContent $content, $id ) {
		$testMemberList = new CollaborationListContent( '{"columns":[{"items":[{"title":"User:X"}]}]}' );

		$block = "<h3>Meet our members!</h3><p><br />\n" . wfMessage( 'collaborationkit-list-isempty' ) . "\n</p><div class=\"mw-ck-members-buttons\"><span aria-disabled='false' class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='/wiki/index.php/Main_Page/Members' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon'></span><span class='oo-ui-labelElement-label'>". wfMessage( 'collaborationkit-hub-members-view' ) . "</span><span class='oo-ui-indicatorElement-indicator'></span></a></span><span aria-disabled='false' class='mw-ck-members-join oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-flaggedElement-primary oo-ui-flaggedElement-progressive oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='/wiki/index.php?title=Main_Page/Members&amp;action=edit' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-image-invert'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'collaborationkit-hub-members-signup' ) . "</span><span class='oo-ui-indicatorElement-indicator oo-ui-image-invert'></span></a></span></div>";
		$expected = [ $block, $block, $block ];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getMembersBlock( Title::newMainPage(), new ParserOptions, new ParserOutput, $testMemberList );
		static::assertEquals( $expected[ $id ], $actual, $id );

	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetParsedAnnouncements( CollaborationHubContent $content, $id ) {
		$testAnnouncement = "* The cafeteria is out of empanadas. We apologize for the inconvenience.";

		$block = "<h3>" . wfMessage( 'collaborationkit-hub-pagetitle-announcements' ) . "</h3><span aria-disabled='false' style='display:block;' class='mw-ck-hub-section-button oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-frameless oo-ui-iconElement oo-ui-labelElement oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='/wiki/index.php?title=Main_Page/Announcements&amp;action=edit' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-icon-edit'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'Edit' ) . "</span><span class='oo-ui-indicatorElement-indicator'></span></a></span>* The cafeteria is out of empanadas. We apologize for the inconvenience.";
		$expected = [ $block, $block, $block ];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getParsedAnnouncements( Title::newMainPage(), new ParserOptions, $testAnnouncement );
		static::assertEquals( $expected[ $id ], $actual, $id );

	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testGetSecondFooter( CollaborationHubContent $content, $id ) {
		global $wgServer;
		$block = "<span aria-disabled='false' class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-iconElement oo-ui-labelElement oo-ui-flaggedElement-progressive oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='/wiki/index.php?title=Main_Page&amp;action=edit' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-icon-edit oo-ui-image-progressive'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'collaborationkit-hub-manage' ) . "</span><span class='oo-ui-indicatorElement-indicator oo-ui-image-progressive'></span></a></span><span aria-disabled='false' class='oo-ui-widget oo-ui-widget-enabled oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-iconElement oo-ui-labelElement oo-ui-flaggedElement-progressive oo-ui-buttonWidget'><a role='button' tabindex='0' aria-disabled='false' href='" . $wgServer . "/wiki/index.php?title=Special:CreateHubFeature&amp;collaborationhub=Main+Page' rel='nofollow' class='oo-ui-buttonElement-button'><span class='oo-ui-iconElement-icon oo-ui-icon-add oo-ui-image-progressive'></span><span class='oo-ui-labelElement-label'>" . wfMessage( 'collaborationkit-hub-addpage' ) . "</span><span class='oo-ui-indicatorElement-indicator oo-ui-image-progressive'></span></a></span>";
		$expected = [ $block, $block, $block ];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->getSecondFooter( Title::newMainPage() );
		static::assertEquals( $expected[ $id ], $actual, $id );

	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testMakeHeader1( CollaborationHubContent $content, $id ) {
		$testContentArray = [ "title" => "Amazing Worklist", "image" => "pageribbon" ];
		$expected = [
			'<div class="mw-ck-hub-section" id="Amazing_Worklist"><h2><span class="mw-headline">Amazing Worklist</span></h2>',
			'<div class="mw-ck-hub-section" id="Amazing_Worklist1"><h2><span class="mw-headline">Amazing Worklist</span></h2>',
			'<div class="mw-ck-hub-section" id="Amazing_Worklist2"><h2><span class="mw-headline">Amazing Worklist</span></h2>'
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->makeHeader( Title::newMainPage(), $testContentArray );
		static::assertEquals( $expected[ $id ], $actual, $id );

	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testMakeHeader2( CollaborationHubContent $content, $id ) {
		$testContentArray = [ "title" => "Gonzo Worklist", "image" => "pageribbon", "displayTitle" => "You Best Believe It" ];
		$expected = [
			'<div class="mw-ck-hub-section" id="You_Best_Believe_It"><h2><span class="mw-headline">You Best Believe It</span></h2>',
			'<div class="mw-ck-hub-section" id="You_Best_Believe_It1"><h2><span class="mw-headline">You Best Believe It</span></h2>',
			'<div class="mw-ck-hub-section" id="You_Best_Believe_It2"><h2><span class="mw-headline">You Best Believe It</span></h2>'
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->makeHeader( Title::newMainPage(), $testContentArray );
		static::assertEquals( $expected[ $id ], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToHumanEditable( CollaborationHubContent $content, $id ) {
		$spl = CollaborationKitSerialization::SERIALIZATION_SPLIT;
		$expected = [
			"foo" . $spl . "''Test'' content" . $spl . "'''Test''' content footer" . $spl . "none" . $spl . "khaki" . $spl . "Project:Wow|image=cool.png|display_title=Wow!\n",
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl,
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->convertToHumanEditable();
		static::assertEquals( $expected[ $id ], $actual, $id );
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
				'colour' => 'khaki',
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
			"foo" . $spl . "''Test'' content" . $spl . "'''Test''' content footer" . $spl . "none" . $spl . "khaki" . $spl . "Project:Wow|image=cool.png|display_title=Wow!",
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl,
			$spl . $spl . $spl . "none" . $spl . "lightgrey" . $spl
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = FormatJson::encode( $wc->convertFromHumanEditable( $testCases[ $id ] ), true, FormatJson::ALL_OK );
		static::assertEquals( $expected[ $id ], $actual, $id );
	}

}
