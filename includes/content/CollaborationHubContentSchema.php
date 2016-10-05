<?php
return [
	'type' => 'object',
	'properties' =>
	[
		'introduction' =>
		[
			'type' => 'string',
		],
		'footer' =>
		[
			'type' => 'string',
		],
		'display_name' =>
		[
			'type' => 'string',
		],
		'image' =>
		[
			'type' => 'string',
		],
		'colour' =>
		[
			'enum' =>
			[
				0 => 'red1',
				1 => 'red2',
				2 => 'grey1',
				3 => 'grey2',
				4 => 'blue1',
				5 => 'blue2',
				6 => 'blue3',
				7 => 'blue4',
				8 => 'blue5',
				9 => 'blue6',
				10 => 'purple1',
				11 => 'purple2',
				12 => 'purple3',
				13 => 'purple4',
				14 => 'purple5',
				15 => 'yellow1',
				16 => 'yellow2',
				17 => 'yellow3',
				18 => 'yellow4',
				19 => 'green1',
				20 => 'green2',
				21 => 'green3',
				22 => 'black',
			],
		],
		'content' =>
		[
			'type' => 'array',
			'items' =>
			[
				[
					'type' => 'object',
					'properties' =>
					[
							'title' =>
							[
								'type' => 'string',
							],
							'image' =>
							[
								'type' => 'string',
							],
							'display_title' =>
							[
								'type' => 'string',
							],
						],
					'required' =>
					[
						0 => 'title',
					],
				],
			],
		],
		'scope' =>
		[
			'type' => 'object',
			'properties' =>
			[
				'included_categories' =>
				[
					'type' => 'array',
					'items' =>
					[
						[
							'type' => 'object',
							'properties' =>
							[
								'category_name' =>
								[
									'type' => 'string'
								],
								'category_depth' =>
								[
									'type' => 'number',
									'default' => 9
								]
							]
						]
					]
				],
				'excluded_categories' =>
				[
					'type' => 'array',
					'items' =>
					[
						[
							'type' => 'object',
							'properties' =>
							[
								'category_name' =>
								[
									'type' => 'string'
								],
								'category_depth' =>
								[
									'type' => 'number',
									'default' => 9
								]
							]
						]
					]
				],
				'included_pages' =>
				[
					'type' => 'array',
					'items' =>
					[
						[
							'type' => 'string'
						]
					]
				],
				'excluded_pages' =>
				[
					'type' => 'array',
					'items' =>
					[
						[
							'type' => 'string'
						]
					]
				]
			]
		]
	],
	'required' =>
	[
		0 => 'content',
	],
];
