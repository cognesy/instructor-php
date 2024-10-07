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
        'symfony' => [
            'httpClientType' => HttpClientType::Symfony->value,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
        ],
        'guzzle-ollama' => [
            'httpClientType' => HttpClientType::Guzzle->value,
            'connectTimeout' => 5,
            'requestTimeout' => 90,
        ],
    ]
];
