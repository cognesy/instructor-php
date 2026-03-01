<?php declare(strict_types=1);

use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigurationException;

function failingProviderForSuppressionRegression(): CanProvideConfig {
    return new class implements CanProvideConfig {
        public function get(string $path, mixed $default = null): mixed {
            throw new RuntimeException('provider failure');
        }

        public function has(string $path): bool {
            throw new RuntimeException('provider failure');
        }
    };
}

// Guards regression from instructor-5koc (mutable withSuppressedProviderErrors side effects).
it('returns a new resolver when toggling provider error suppression', function () {
    $base = ConfigResolver::using(failingProviderForSuppressionRegression());
    $strict = $base->withSuppressedProviderErrors(false);

    expect($strict)->not->toBe($base);
});

it('does not mutate shared resolver instances when suppression mode is changed', function () {
    $base = ConfigResolver::using(failingProviderForSuppressionRegression());
    $shared = $base;
    $strict = $base->withSuppressedProviderErrors(false);

    expect($shared->get('any.path', 'fallback'))->toBe('fallback');
    expect($shared->has('any.path'))->toBeFalse();

    expect(fn() => $strict->get('any.path', 'fallback'))
        ->toThrow(ConfigurationException::class, 'Failed to resolve configuration from provider.');
    expect(fn() => $strict->has('any.path'))
        ->toThrow(ConfigurationException::class, 'Failed to resolve configuration from provider.');
});
