<?php

return [
    'defaultPreset' => 'guzzle',

    'presets' => [
        'guzzle' => [
            'driver' => 'guzzle',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'streamChunkSize' => 256,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'symfony' => [
            'driver' => 'symfony',
            'connectTimeout' => 10,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'streamChunkSize' => 0,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'laravel' => [
            'driver' => 'laravel',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'streamChunkSize' => 256,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'http-ollama' => [
            'driver' => 'guzzle',
            'connectTimeout' => 3,
            'requestTimeout' => 90,
            'streamChunkSize' => 256,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
    ],
];
