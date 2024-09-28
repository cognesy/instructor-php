<?php

use Cognesy\Instructor\Extras\Enums\HttpClientType;

return [
    'defaultClient' => 'guzzle',

    'cache' => [
        'enabled' => false,
        'expiryInSeconds' => 3600,
        'path' => '/tmp/instructor/cache',
    ],

    'clients' => [
        'guzzle' => [
            'httpClientType' => HttpClientType::Guzzle->value,
            'connectTimeout' => 3,
            'requestTimeout' => 30,
        ],
    ]
];
