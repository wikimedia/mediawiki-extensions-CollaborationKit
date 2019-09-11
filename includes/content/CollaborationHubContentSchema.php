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
				0 => 'red',
				1 => 'lightgrey',
				2 => 'skyblue',
				3 => 'bluegrey',
				4 => 'aquamarine',
				5 => 'violet',
				6 => 'salmon',
				7 => 'yellow',
				8 => 'gold',
				9 => 'brightgreen',
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
