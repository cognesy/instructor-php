<?php

use Cognesy\Instructor\Features\Http\Enums\HttpClientType;

return [
    'defaultClient' => 'laravel',

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
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
        ],
        'laravel' => [
            'httpClientType' => HttpClientType::Laravel->value,
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
        ],
        'http-ollama' => [
            'httpClientType' => HttpClientType::Guzzle->value,
            'connectTimeout' => 1,
            'requestTimeout' => 90,
            'idleTimeout' => -1,
        ],
    ]
];
