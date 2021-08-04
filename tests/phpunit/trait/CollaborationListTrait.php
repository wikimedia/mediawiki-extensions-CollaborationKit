<?php

trait CollaborationListTrait {

	/**
	 * Helper function to shorten lines
	 * @param string $text
	 * @return CollaborationHubContent
	 */
	private function m( $text ) {
		return new CollaborationListContent( $text );
	}

	/**
	 * Helper function for test cases
	 * @param mixed $arr
	 * @return string
	 */
	private function stringify( $arr ) {
		return FormatJson::encode( $arr, true, FormatJson::ALL_OK );
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
}
