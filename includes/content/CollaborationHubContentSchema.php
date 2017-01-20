<?php
return [
	'type' => 'object',
	'properties' => [
		'displaymode' => [
			'enum' => [
				0 => 'normal',
				1 => 'error'
			]
		],
		'introduction' => [
			'type' => 'string',
		],
		'footer' => [
			'type' => 'string',
		],
		'display_name' => [
			'type' => 'string',
		],
		'image' => [
			'type' => 'string',
		],
		'colour' => [
			'enum' => [
				0 => 'darkred',
				1 => 'red',
				2 => 'darkgrey',
				3 => 'lightgrey',
				4 => 'skyblue',
				5 => 'blue',
				6 => 'bluegrey',
				7 => 'navyblue',
				8 => 'darkblue',
				9 => 'aquamarine',
				10 => 'violet',
				11 => 'purple',
				12 => 'mauve',
				13 => 'lightmauve',
				14 => 'salmon',
				15 => 'orange',
				16 => 'yellow',
				17 => 'gold',
				18 => 'pastelyellow',
				19 => 'forestgreen',
				20 => 'brightgreen',
				21 => 'khaki',
				22 => 'black'
			],
		],
		'content' => [
			'type' => 'array',
			'items' => [ [
				'type' => 'object',
				'properties' => [
					'title' => [
						'type' => 'string',
					],
					'image' => [
						'type' => 'string',
					],
					'display_title' => [
						'type' => 'string',
					],
				],
				'required' => [
					0 => 'title',
				],
			] ],
		],
		'scope' => [
			'type' => 'object',
			'properties' => [
				'included_categories' => [
					'type' => 'array',
					'items' => [ [
						'type' => 'object',
						'properties' => [
							'category_name' => [
								'type' => 'string'
							],
							'category_depth' => [
								'type' => 'number',
								'default' => 9
							]
						]
					] ]
				],
				'excluded_categories' => [
					'type' => 'array',
					'items' => [ [
						'type' => 'object',
						'properties' => [
							'category_name' => [
								'type' => 'string'
							],
							'category_depth' => [
								'type' => 'number',
								'default' => 9
							]
						]
					] ]
				],
				'included_pages' => [
					'type' => 'array',
					'items' => [ [
						'type' => 'string'
					] ]
				],
				'excluded_pages' => [
					'type' => 'array',
					'items' => [ [
						'type' => 'string'
					] ]
				]
			]
		]
	],
	'required' => [
		0 => 'content',
	],
];
