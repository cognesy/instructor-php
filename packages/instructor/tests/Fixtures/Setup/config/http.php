<?php

/**
 * @deprecated Legacy preset-style PHP config for backward compatibility.
 * Migrate to package-scoped YAML config under resources/config/**.
 */


return [
    'defaultPreset' => 'guzzle',

    'presets' => [
        'guzzle' => [
            'httpClientDriver' => 'guzzle',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'failOnError' => true,
        ],
        'symfony' => [
            'httpClientDriver' => 'symfony',
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'failOnError' => true,
        ],
        'http-ollama' => [
            'httpClientDriver' => 'guzzle',
            'connectTimeout' => 1,
            'requestTimeout' => 90,
            'idleTimeout' => -1,
            'failOnError' => true,
        ],
    ],
];
