<?php

return [
    'defaultPreset' => 'guzzle',

    'presets' => [
        'guzzle' => [
            'driver' => 'guzzle',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'symfony' => [
            'driver' => 'symfony',
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'laravel' => [
            'driver' => 'laravel',
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'http-ollama' => [
            'driver' => 'guzzle',
            'connectTimeout' => 1,
            'requestTimeout' => 90,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
    ],
];
