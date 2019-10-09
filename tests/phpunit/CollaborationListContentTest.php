<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @covers CollaborationListContent
 */
class CollaborationListContentTest extends MediaWikiTestCase {

	private $content;

	public function setUp() : void {
		parent::setUp();
		$content = new CollaborationListContent(
			'{"description": "", "displaymode": "normal", "options": { "mode": "normal" }, "columns": [ { "items": [ { "title": "Wow" } ] } ] }'
		);
		$this->content = TestingAccessWrapper::newFromObject( $content );
	}

	/**
	 * Helper function to shorten lines
	 */
	private function m( $text ) {
		return new CollaborationListContent( $text );
	}

	/**
	 * Helper function for test cases
	 */
	private function stringify( $arr ) {
		return FormatJson::encode( $arr, true, FormatJson::ALL_OK );
	}

	private function getMockTitle() {
		return $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getMockUser() {
		return $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function getMockParserOptions() {
		return $this->getMockBuilder( ParserOptions::class )
			->disableOriginalConstructor()
			->getMock();
	}

	public function provideContentObjs() {
		// TODO: Test more option settings

		// Empty list tests
		$test0 = ( new CollaborationListContentHandler )->makeEmptyContent();
		$test1 = CollaborationListContentHandler::makeMemberList( 'Test', '' );

		// Single column tests
		$test2 = [
			'description' => 'test',
			'displaymode' => 'normal',
			'options' => [ 'mode' => 'normal' ],
			'columns' => [
				[
					'label' => 'Super Column One',
					'notes' => 'Amazing Notes',
					'items' => [
						[ 'title' => 'Billy', 'notes' => 'It is Billy!', 'image' => 'Samoyed.jpg' ],
						[ 'title' => 'Nondescript Bystander' ]
					]
				]
			]
		];

		$test3 = [
			'description' => 'Our members!',
			'displaymode' => 'members',
			'options' => [ 'mode' => 'normal' ],
			'columns' => [
				[
					'items' => [
						[ 'title' => 'User:Jim', 'notes' => 'Jim!', 'image' => 'Example.svg' ],
						[ 'title' => 'User:Mac' ]
					]
				]
			]
		];

		// Multi-column test

		$test4 = [
			'description' => 'test',
			'displaymode' => 'normal',
			'options' => [ 'mode' => 'normal' ],
			'columns' => [
				[
					'label' => 'Super Column One',
					'notes' => 'Amazing Notes',
					'items' => [
						[ 'title' => 'Billy', 'notes' => 'It is Billy!', 'image' => 'Samoyed.jpg' ],
						[ 'title' => 'Nondescript Bystander' ]
					]
				],
				[
					'items' => [
						[ 'title' => 'Francis Bacon', 'image' => 'Short-haired calico.jpg' ]
					]
				]
			]
		];

		return [
			[ $test0, 0 ],
			[ $test1, 1 ],
			[ $this->m( $this->stringify( $test2 ) ), 2 ],
			[ $this->m( $this->stringify( $test3 ) ), 3 ],
			[ $this->m( $this->stringify( $test4 ) ), 4 ],
		];
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testIsValid( CollaborationListContent $content, $id ) {
		static::assertTrue( $content->isValid(), $id );
	}

	public function provideInvalid() {
		return [
			[ '{ afdsfda }' ],
			[ '{ "description": "", "options": { "mode": "normal" }, "displaymode": "", "columns": [ { "items": [] } ] }' ],
			[ '{ "description": "", "options": { "mode": "normal" }, "displaymode": "regular", "columns": [ { "items": [] } ] }' ],
			[ '{ "description": "", "options": { "mode": "normal" }, "displaymode": "normal", "columns": [ { "items": [ "title" ] } ] }' ],
			[ '{ "description": "", "options": { "mode": "normal" }, "displaymode": "normal", "columns": [ { "items": null }' ],
			[ '{ "description": "", "options": { "mode": "normal" }, "displaymode": "normal", "items": [] }' ],
			[ '{ "description": [], "options": { "mode": "normal" }, "displaymode": "normal", "columns": [ { "items": [] } ] }' ],
			[ '{ "description": {}, "options": { "mode": "normal" }, "displaymode": "normal", "columns": [ { "items": [] } ] }' ],
			[ '{ "description": "", "options": { "mode": "normal" }, "columns": [ { "items": [] } ] }' ]
		];
	}

	/**
	 * @dataProvider provideInvalid
	 */
	public function testIsNotValid( $contentText ) {
		$content = new CollaborationListContent( $contentText );
		static::assertFalse( $content->isValid() );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToWikitextVanilla( CollaborationListContent $content, $id ) {
		static::markTestIncomplete(); // pending fix to issues regarding images and tests

		$expected = [
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n\n<div class=\"mw-ck-list mw-ck-singlelist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n</div>\n\n{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n\n<div class=\"mw-ck-list mw-ck-multilist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Active members ===\n</div>\n\n{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"1\">\n<div class=\"mw-ck-list-column-header\">\n=== Inactive members ===\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"User:Test\">"
				. CollaborationKitImage::makeImage( 'user', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'colour' => 'lightgrey', 'link' => false, 'renderAsWikitext' => true ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:User:Test|Test]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div>\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\ntest\n<div class=\"mw-ck-list mw-ck-singlelist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Super Column One ===\n<div class=\"mw-ck-list-notes\">Amazing Notes</div>\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Billy\">"
				. CollaborationKitImage::makeImage( 'Samoyed.jpg', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'link' => true, 'renderAsWikitext' => true, 'testImage' => 'File:Samoyed.jpg' ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Billy|Billy]]</div>\n<div class=\"mw-ck-list-notes\">\nIt is Billy!\n</div></div></div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Nondescript Bystander\">"
				. CollaborationKitImage::makeImage( 'page', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'colour' => 'lightgrey', 'link' => false, 'renderAsWikitext' => true ] ) . "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Nondescript_Bystander|Nondescript Bystander]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div>\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\nOur members!\n<div class=\"mw-ck-list mw-ck-multilist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Active members ===\n</div>\n\n{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"1\">\n<div class=\"mw-ck-list-column-header\">\n=== Inactive members ===\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"User:Jim\">"
				. CollaborationKitImage::makeImage( 'Example.svg', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'renderAsWikitext' => true ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:User:Jim|Jim]]</div>\n<div class=\"mw-ck-list-notes\">\nJim!\n</div></div></div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"User:Mac\">"
				. CollaborationKitImage::makeImage( 'user', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'colour' => 'lightgrey', 'link' => true, 'renderAsWikitext' => true ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:User:Mac|Mac]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div>\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\ntest\n<div class=\"mw-ck-list mw-ck-multilist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Super Column One ===\n<div class=\"mw-ck-list-notes\">Amazing Notes</div>\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Billy\">"
				. CollaborationKitImage::makeImage( 'Samoyed.jpg', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'renderAsWikitext' => true ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Billy|Billy]]</div>\n<div class=\"mw-ck-list-notes\">\nIt is Billy!\n</div></div></div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Nondescript Bystander\">"
				. CollaborationKitImage::makeImage( 'page', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'colour' => 'lightgrey', 'link' => false ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Nondescript_Bystander|Nondescript Bystander]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div><div class=\"mw-ck-list-column\" data-collabkit-column-id=\"1\">\n<div class=\"mw-ck-list-column-header\">\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Francis Bacon\">"
				. CollaborationKitImage::makeImage( 'Short-haired calico.jpg', 64, [ 'classes' => [ 'mw-ck-list-image' ], 'renderAsWikitext' => true ] )
				. "<div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Francis_Bacon|Francis Bacon]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div>\n</div>"
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->convertToWikitext( Language::factory( 'en' ), [ 'includeDesc' => true, 'maxItems' => false, 'defaultSort' => 'natural' ] );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToWikitextWithVariants( CollaborationListContent $content, $id ) {
		static::markTestIncomplete(); // pending fix to issues regarding images and tests

		$expected = [
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n<div class=\"mw-ck-list mw-ck-singlelist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n</div>\n\n{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n<div class=\"mw-ck-list mw-ck-multilist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Active members ===\n</div>\n\n{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"1\">\n<div class=\"mw-ck-list-column-header\">\n=== Inactive members ===\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"User:Test\"><div class=\"mw-ck-list-image\" style=\"\"><div class=\"mw-ck-icon mw-ck-icon-user-lightgrey\" style=\"width: 32px; height: 32px;\"></div></div><div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:User:Test|Test]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div>\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n<div class=\"mw-ck-list mw-ck-singlelist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Super Column One ===\n<div class=\"mw-ck-list-notes\">Amazing Notes</div>\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Billy\"><div class=\"mw-ck-list-image\" style=\"\">[[File:Samoyed.jpg|32px]]</div><div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Billy|Billy]]</div>\n<div class=\"mw-ck-list-notes\">\nIt is Billy!\n</div></div></div>\n\n</div>\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n<div class=\"mw-ck-list mw-ck-multilist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Active members ===\n</div>\n\n{{mediawiki:collaborationkit-list-emptycolumn}}</div>\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"1\">\n<div class=\"mw-ck-list-column-header\">\n=== Inactive members ===\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"User:Jim\"><div class=\"mw-ck-list-image\" style=\"\">[[File:Example.svg|32px]]</div><div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:User:Jim|Jim]]</div>\n<div class=\"mw-ck-list-notes\">\nJim!\n</div></div></div>\n\n</div>\n</div>",
			"<collaborationkitloadliststyles/>__NOTOC__ __NOEDITSECTION__\n<div class=\"mw-ck-list mw-ck-multilist\">\n<div class=\"mw-ck-list-column\" data-collabkit-column-id=\"0\">\n<div class=\"mw-ck-list-column-header\">\n=== Super Column One ===\n<div class=\"mw-ck-list-notes\">Amazing Notes</div>\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Billy\"><div class=\"mw-ck-list-image\" style=\"\">[[File:Samoyed.jpg|32px]]</div><div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Billy|Billy]]</div>\n<div class=\"mw-ck-list-notes\">\nIt is Billy!\n</div></div></div>\n\n</div><div class=\"mw-ck-list-column\" data-collabkit-column-id=\"1\">\n<div class=\"mw-ck-list-column-header\">\n</div>\n<div class=\"mw-ck-list-item\" data-collabkit-item-title=\"Francis Bacon\"><div class=\"mw-ck-list-image\" style=\"\">[[File:Short-haired calico.jpg|32px]]</div><div class=\"mw-ck-list-container\"><div class=\"mw-ck-list-title\">[[:Francis_Bacon|Francis Bacon]]</div>\n<div class=\"mw-ck-list-notes\">\n</div></div></div>\n\n</div>\n</div>"
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->convertToWikitext( Language::factory( 'en' ), [ 'includeDesc' => false, 'maxItems' => 1, 'defaultSort' => 'natural', 'iconWidth' => 32 ] );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToHumanEditable( CollaborationListContent $content, $id ) {
		$spl1 = CollaborationKitSerialization::SERIALIZATION_SPLIT;
		$spl2 = CollaborationListContent::HUMAN_COLUMN_SPLIT;
		$spl3 = CollaborationListContent::HUMAN_COLUMN_SPLIT2;
		$expected = [
			$spl1 . "DISPLAYMODE=normal\n" . $spl1 . $spl2 . 'column' . $spl3,
			$spl1 . "mode=normal\nDISPLAYMODE=members\n" . $spl1 . $spl2 . 'column' . $spl3 . "User:Test\n",
			'test' . $spl1 . "mode=normal\nDISPLAYMODE=normal\n" . $spl1 . $spl2 . 'Super Column One|notes=Amazing Notes' . $spl3 . "Billy|It is Billy!|image=Samoyed.jpg\nNondescript Bystander\n",
			'Our members!' . $spl1 . "mode=normal\nDISPLAYMODE=members\n" . $spl1 . $spl2 . 'column' . $spl3 . "User:Jim|Jim!|image=Example.svg\nUser:Mac\n",
			'test' . $spl1 . "mode=normal\nDISPLAYMODE=normal\n" . $spl1 . $spl2 . 'Super Column One|notes=Amazing Notes' . $spl3 . "Billy|It is Billy!|image=Samoyed.jpg\nNondescript Bystander\n" . $spl2 . 'column' . $spl3 . "Francis Bacon||image=Short-haired calico.jpg\n"
		];
		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->convertToHumanEditable();
		static::assertEquals( $expected[$id], $actual, $id );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertFromHumanEditable( CollaborationListContent $content, $id ) {
		$spl1 = CollaborationKitSerialization::SERIALIZATION_SPLIT;
		$spl2 = CollaborationListContent::HUMAN_COLUMN_SPLIT;
		$spl3 = CollaborationListContent::HUMAN_COLUMN_SPLIT2;

		$testCases = [
			$spl1 . "DISPLAYMODE=normal\n" . $spl1 . $spl2 . 'column' . $spl3,
			$spl1 . "mode=normal\nDISPLAYMODE=members\n" . $spl1 . $spl2 . 'column' . $spl3 . "User:Test\n",
			'test' . $spl1 . "mode=normal\nDISPLAYMODE=normal\n" . $spl1 . $spl2 . 'Super Column One|notes=Amazing Notes' . $spl3 . "Billy|It is Billy!|image=Samoyed.jpg\nNondescript Bystander\n",
			'Our members!' . $spl1 . "mode=normal\nDISPLAYMODE=members\n" . $spl1 . $spl2 . 'column' . $spl3 . "User:Jim|Jim!|image=Example.svg\nUser:Mac\n",
			'test' . $spl1 . "mode=normal\nDISPLAYMODE=normal\n" . $spl1 . $spl2 . 'Super Column One|notes=Amazing Notes' . $spl3 . "Billy|It is Billy!|image=Samoyed.jpg\nNondescript Bystander\n" . $spl2 . 'column' . $spl3 . "Francis Bacon||image=Short-haired calico.jpg\n"
		];

		$expected = [
			[
				'displaymode' => 'normal',
				'columns' => [ [ 'items' => [] ] ],
				'options' => (object)[],
				'description' => ''
			],
			[
				'displaymode' => 'members',
				'columns' => [ [
					'items' => [ [
						'title' => 'User:Test',
						'notes' => ''
					] ]
				] ],
				'options' => (object)[
					'mode' => 'normal'
				],
				'description' => ''
			],
			[
				'description' => 'test',
				'displaymode' => 'normal',
				'options' => (object)[ 'mode' => 'normal' ],
				'columns' => [
					[
						'label' => 'Super Column One',
						'notes' => 'Amazing Notes',
						'items' => [
							[ 'title' => 'Billy', 'notes' => 'It is Billy!', 'image' => 'Samoyed.jpg' ],
							[ 'title' => 'Nondescript Bystander', 'notes' => '' ]
						]
					]
				]
			],
			[
				'description' => 'Our members!',
				'displaymode' => 'members',
				'options' => (object)[ 'mode' => 'normal' ],
				'columns' => [
					[
						'items' => [
							[ 'title' => 'User:Jim', 'notes' => 'Jim!', 'image' => 'Example.svg' ],
							[ 'title' => 'User:Mac', 'notes' => '' ]
						]
					]
				]
			],
			[
				'description' => 'test',
				'displaymode' => 'normal',
				'options' => (object)[ 'mode' => 'normal' ],
				'columns' => [
					[
						'label' => 'Super Column One',
						'notes' => 'Amazing Notes',
						'items' => [
							[ 'title' => 'Billy', 'notes' => 'It is Billy!', 'image' => 'Samoyed.jpg' ],
							[ 'title' => 'Nondescript Bystander', 'notes' => '' ]
						]
					],
					[
						'items' => [
							[ 'title' => 'Francis Bacon', 'image' => 'Short-haired calico.jpg', 'notes' => '' ]
						]
					]
				]
			]
		];

		$wc = TestingAccessWrapper::newFromObject( $content );
		$actual = $wc->convertFromHumanEditable( $testCases[ $id ] );
		static::assertEquals( $expected[$id], $actual, $id );
	}

	public function provideMatchesTag() {
		return [
			[
				[ [ 'a' ] ],
				[ 'a' ],
				true,
			],
			[
				[ [ 'a' ] ],
				[ 'b' ],
				false,
			],
			[
				[ [ 'a', 'b' ] ],
				[ 'a' ],
				false,
			],
			[
				[ [ 'a', 'b' ] ],
				[ 'a', 'b', 'c' ],
				true,
			],
			[
				[ [ 'a', 'b' ], [ 'a', 'c' ] ],
				[ 'a', 'c' ],
				true,
			],
			[
				[ [ 'a' ], [ 'b' ] ],
				[ 'b' ],
				true,
			],
		];
	}

	/**
	 * @dataProvider provideMatchesTag
	 */
	public function testMatchesTag( $tagSpecifier, $itemTags, $expected ) {
		$actual = $this->content->matchesTag( $tagSpecifier, $itemTags );
		static::assertEquals( $expected, $actual );
	}

	public function testDecodeInvalidJSON() {
		$content = new CollaborationListContent( 'NOT JSON' );
		static::assertFalse( $content->isValid() );
		// Force decode() call
		static::assertNull( $content->getDescription() );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testTextNativeDataEquivalent( CollaborationListContent $content, $id ) {
		if ( !method_exists( $content, 'getNativeData' ) ) {
			static::markTestSkipped( 'getNativeData() no longer present. Skipping comparison.' );
		}

		static::assertEquals( $content->getNativeData(), $content->getText(),
			'Call to NativeData() does not match call to getText()' );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testConvertToJson( CollaborationListContent $content, $id ) {
		$json = $content->convert( CONTENT_MODEL_JSON );

		static::assertInstanceOf( JsonContent::class, $json, 'Expected JsonContent' );
		static::assertTrue( $json->IsValid(), 'Expected valid content' );
	}

	/**
	 * @dataProvider provideContentObjs
	 */
	public function testPreSaveTransform( CollaborationListContent $content, $id ) {
		$output = $content->preSaveTransform( $this->getMockTitle(), $this->getMockUser(),
			$this->getMockParserOptions() );

		static::assertInstanceOf( CollaborationListContent::class, $output );
		static::assertFalse( $content === $output,
			'Method should have returned new object with formatted JSON' );
	}

	public function testPreSaveTransformInvalidJSON() {
		$content = new CollaborationListContent( 'NOT JSON' );

		$output = $content->preSaveTransform( $this->getMockTitle(), $this->getMockUser(),
			$this->getMockParserOptions() );

		static::assertInstanceOf( CollaborationListContent::class, $output );
		static::assertTrue( $content === $output,
			'Method should have returned object itself due to invalid content' );
	}
}
