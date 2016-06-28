<?php
return (object) [
    'post'  => [
        'firstName' => 'John',
        'lastName'  => 'Smith',
        'age'       => '25',
        'street'    => '134 2nd Street',
        'city'      => 'New York',
        'state'     => 'NY',
        'zipcode'   => '10021',
        'phoneNum'  => [
            [
              'type'   => 'work',
              'number' => '212 555-1234'
            ],
            [
              'type'   => 'cell',
              'number' => '646 555-4567'
            ]
        ]
    ],
    'files' => [
        'singlefile'    => [
            'name'     => 'blue.gif',
            'type'     => 'image/gif',
            'tmp_name' => '/tmp/php5x610y',
            'error'    => 0,
            'size'     => 100
        ],
        'manyfiles'     => [
            'name'     => ['black.gif',      'green.gif'     ],
            'type'     => ['image/gif',      'image/gif'     ],
            'tmp_name' => ['/tmp/phpBRR5Ir', '/tmp/phpr229qk'],
            'error'    => [0,                0               ],
            'size'     => [58,               85              ]
        ],
        'singleinmulti' => [
            'name'     => ['black.gif'     ],
            'type'     => ['image/gif'     ],
            'tmp_name' => ['/tmp/phpIZDe9c'],
            'error'    => [0               ],
            'size'     => [58              ]
        ]
    ]
];