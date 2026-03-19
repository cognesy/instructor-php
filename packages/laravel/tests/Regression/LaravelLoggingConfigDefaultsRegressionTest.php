<?php declare(strict_types=1);

it('ships production-safe logging defaults in laravel config', function () {
    $previousLevel = getenv('INSTRUCTOR_LOG_LEVEL');
    $previousPreset = getenv('INSTRUCTOR_LOGGING_PRESET');

    putenv('INSTRUCTOR_LOG_LEVEL');
    putenv('INSTRUCTOR_LOGGING_PRESET');
    unset($_ENV['INSTRUCTOR_LOG_LEVEL'], $_ENV['INSTRUCTOR_LOGGING_PRESET']);
    unset($_SERVER['INSTRUCTOR_LOG_LEVEL'], $_SERVER['INSTRUCTOR_LOGGING_PRESET']);

    $config = require __DIR__ . '/../../resources/config/instructor.php';

    try {
        expect($config['logging']['level'])->toBe('warning')
            ->and($config['logging']['preset'])->toBe('production')
            ->and($config['logging']['exclude_events'])->toContain(
                Cognesy\Http\Events\DebugRequestBodyUsed::class,
                Cognesy\Http\Events\DebugResponseBodyReceived::class,
                Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated::class,
                Cognesy\Polyglot\Inference\Events\StreamEventParsed::class,
            );
    } finally {
        match (true) {
            $previousLevel === false => putenv('INSTRUCTOR_LOG_LEVEL'),
            default => putenv("INSTRUCTOR_LOG_LEVEL={$previousLevel}"),
        };

        match (true) {
            $previousPreset === false => putenv('INSTRUCTOR_LOGGING_PRESET'),
            default => putenv("INSTRUCTOR_LOGGING_PRESET={$previousPreset}"),
        };
    }
});
