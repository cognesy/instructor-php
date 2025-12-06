<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Instructor Logging Configuration
    |--------------------------------------------------------------------------
    */

    'enabled' => env('INSTRUCTOR_LOGGING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Preset Configuration
    |--------------------------------------------------------------------------
    |
    | Available presets:
    | - default: Standard logging with framework context
    | - production: Minimal logging for production environments
    | - custom: Use custom config below
    */

    'preset' => env('INSTRUCTOR_LOGGING_PRESET', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Custom Configuration
    |--------------------------------------------------------------------------
    |
    | Used when preset is set to 'custom'
    */

    'config' => [
        'channel' => env('INSTRUCTOR_LOG_CHANNEL', 'instructor'),
        'level' => env('INSTRUCTOR_LOG_LEVEL', 'debug'),

        'exclude_events' => [
            // Cognesy\HttpClient\Events\DebugRequestBodyUsed::class,
            // Cognesy\HttpClient\Events\DebugResponseBodyReceived::class,
        ],

        'include_events' => [
            // Only log these events (leave empty to log all)
        ],

        'templates' => [
            \Cognesy\Instructor\Events\StructuredOutputStarted::class =>
                'Starting {responseClass} generation with {model}',
            \Cognesy\Instructor\Events\ResponseValidationFailed::class =>
                'Validation failed for {responseClass}: {error}',
            \Cognesy\HttpClient\Events\HttpRequestSent::class =>
                'HTTP {method} {url}',
            \Cognesy\HttpClient\Events\HttpResponseReceived::class =>
                'HTTP {method} {url} → {status_code}',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Additional log channels for Instructor
    */

    'channels' => [
        'instructor' => [
            'driver' => 'daily',
            'path' => storage_path('logs/instructor.log'),
            'level' => env('INSTRUCTOR_LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'instructor-errors' => [
            'driver' => 'single',
            'path' => storage_path('logs/instructor-errors.log'),
            'level' => 'error',
        ],

        'instructor-http' => [
            'driver' => 'daily',
            'path' => storage_path('logs/instructor-http.log'),
            'level' => 'debug',
            'days' => 7,
        ],
    ],
];