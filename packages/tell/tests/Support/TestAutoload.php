<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support;

final readonly class TestAutoload
{
    public static function path(): string
    {
        $configuredAutoload = getenv('TELL_TEST_AUTOLOAD');

        return match (true) {
            is_string($configuredAutoload) && $configuredAutoload !== '' => $configuredAutoload,
            default => dirname(__DIR__, 2) . '/vendor/autoload.php',
        };
    }
}
