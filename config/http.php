<?php

use Cognesy\Instructor\Extras\Enums\HttpClientType;

return [
    'defaultClient' => 'symfony',

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
            'connectTimeout' => 90, // Symfony HttpClient does not allow to set connect timeout, set it to request timeout
            'requestTimeout' => 90,
        ],
    ]
];
