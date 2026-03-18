<?php

return [
    'enabled' => env('INSTRUCTOR_LOGGING_ENABLED', true),
    'preset' => env('INSTRUCTOR_LOGGING_PRESET', 'default'),
    'event_bus_binding' => \Cognesy\Events\Contracts\CanHandleEvents::class,
    'config' => [
        'channel' => env('INSTRUCTOR_LOG_CHANNEL', 'instructor'),
        'level' => env('INSTRUCTOR_LOG_LEVEL', 'debug'),
        'exclude_events' => [
            // \Cognesy\Http\Events\DebugRequestBodyUsed::class,
            // \Cognesy\Http\Events\DebugResponseBodyReceived::class,
        ],
        'include_events' => [],
        'templates' => [
            \Cognesy\Instructor\Events\StructuredOutputStarted::class =>
                'Starting {responseClass} generation with {model}',
            \Cognesy\Instructor\Events\ResponseValidationFailed::class =>
                'Validation failed for {responseClass}: {error}',
            \Cognesy\Http\Events\HttpRequestSent::class =>
                'HTTP {method} {url}',
            \Cognesy\Http\Events\HttpResponseReceived::class =>
                'HTTP {method} {url} → {status_code}',
        ],
    ],
    // NOTE: INSTRUCTOR_LOG_PATH (the standalone JSONL env var) has no effect here.
    // Laravel always provides its own CanHandleEvents dispatcher, so EventLog::root()
    // is never called. For structured JSON output, configure a Laravel log channel
    // with Monolog\Formatter\JsonFormatter and point INSTRUCTOR_LOG_CHANNEL at it.
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
