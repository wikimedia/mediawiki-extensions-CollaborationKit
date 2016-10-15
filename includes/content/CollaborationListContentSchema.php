<?php
return [
	'type' => 'object',
	'required' => [ 'description', 'items', 'options' ],
	'properties' => [
		'displaymode' => [
			'enum' => [
				0 => 'normal',
				1 => 'members',
				2 => 'error'
			]
		],
		'errortext' => [
			'type' => 'string'
		],
		'description' => [
			'type' => 'string'
		],
		'items' => [
			'type' => 'array',
			'maxItems' => 2000,
			'items' => [ [
				'type' => 'object',
				'required' => [ 'title' ],
				'properties' => [
					'title' => [
						'type' => 'string'
					],
					'link' => [
						'type' => 'string'
					],
					'notes' => [
						'type' => 'string'
					],
					'image' => [
						'type' => 'string'
					],
					'sortkey' => [
						'type' => 'object',
						'properties' => [
							'criterianame' => [
								'type' => 'string'
							],
							'value' => [
								'type' => 'string'
							]
						]
					],
					'tags' => [
						'type' => 'array',
						'maxItems' => 50,
						'items' => [ [
							'type' => 'string'
						] ]
					]
				]
			] ]
		],
		'options' => [
			'type' => 'object',
			'properties' => [
				'ismemberlist' => [
					'type' => 'boolean',
					'default' => false
				],
				'memberoptions' => [
					'type' => 'object',
					'properties' => [
						'inactivethreshold' => [
							'type' => 'number',
							'default' => 30
						]
					]
				],
				'defaultsort' => [
					'type' => 'string',
					'default' => 'random'
				],
				'maxitems' => [
					'type' => 'number',
					'default' => 5
				],
				'includedesc' => [
					'type' => 'boolean',
					'default' => false
				],
				'offset' => [
					'type' => 'number',
					'default' => 0
				],
				'mode' => [
					'type' => 'string',
					'default' => 'normal'
				],
				'tags' => [
					'type' => 'array',
					'items' => [ [] ]
				]
			]
		]
	]
];
