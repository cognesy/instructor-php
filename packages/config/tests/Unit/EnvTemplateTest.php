<?php declare(strict_types=1);

namespace Cognesy\Config\Tests\Unit;

use Cognesy\Config\EnvTemplate;
use InvalidArgumentException;

it('resolves plain placeholders and embedded placeholders', function () {
    $template = new EnvTemplate();

    withEnv('CFG_INTERP_KEY', 'secret', function () use ($template): void {
        expect($template->resolveString('${CFG_INTERP_KEY}'))->toBe('secret');
        expect($template->resolveString('Bearer ${CFG_INTERP_KEY}'))->toBe('Bearer secret');
    });
});

it('uses fallback value for missing placeholders', function () {
    $template = new EnvTemplate();

    withEnv('CFG_INTERP_REGION', null, function () use ($template): void {
        expect($template->resolveString('${CFG_INTERP_REGION:-eu-west-1}'))->toBe('eu-west-1');
    });
});

it('throws for required placeholders and supports custom messages', function () {
    $template = new EnvTemplate();

    withEnv('CFG_INTERP_REQUIRED', null, function () use ($template): void {
        expect(fn () => $template->resolveString('${CFG_INTERP_REQUIRED?}'))
            ->toThrow(InvalidArgumentException::class, "Required environment variable 'CFG_INTERP_REQUIRED' is missing or empty");

        expect(fn () => $template->resolveString('${CFG_INTERP_REQUIRED:?missing cfg value}'))
            ->toThrow(InvalidArgumentException::class, 'missing cfg value');
    });
});

it('resolves nested arrays recursively', function () {
    $template = new EnvTemplate();

    $input = [
        'api' => [
            'url' => 'https://api.${CFG_INTERP_HOST}/v1',
            'key' => '${CFG_INTERP_KEY}',
        ],
        'meta' => [
            'region' => '${CFG_INTERP_REGION:-us-east-1}',
        ],
    ];

    withEnv('CFG_INTERP_HOST', 'example.test', function () use ($template, $input): void {
        withEnv('CFG_INTERP_KEY', 'abc123', function () use ($template, $input): void {
            withEnv('CFG_INTERP_REGION', null, function () use ($template, $input): void {
                $resolved = $template->resolveData($input);

                expect($resolved)->toBe([
                    'api' => [
                        'url' => 'https://api.example.test/v1',
                        'key' => 'abc123',
                    ],
                    'meta' => [
                        'region' => 'us-east-1',
                    ],
                ]);
            });
        });
    });
});

function withEnv(string $name, ?string $value, callable $callback): void {
    $previousValue = getenv($name);
    $hadPrevious = $previousValue !== false || array_key_exists($name, $_ENV);
    $previousEnv = $_ENV[$name] ?? null;

    if ($value === null) {
        putenv($name);
        unset($_ENV[$name]);
    } else {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }

    try {
        $callback();
    } finally {
        if ($hadPrevious && $previousValue !== false) {
            putenv("{$name}={$previousValue}");
            $_ENV[$name] = (string) $previousValue;
            return;
        }

        if ($hadPrevious && is_string($previousEnv)) {
            putenv("{$name}={$previousEnv}");
            $_ENV[$name] = $previousEnv;
            return;
        }

        putenv($name);
        unset($_ENV[$name]);
    }
}
