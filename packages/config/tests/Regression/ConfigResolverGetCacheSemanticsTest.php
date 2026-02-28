<?php declare(strict_types=1);

use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigurationException;

function missingOnlyProvider(): CanProvideConfig {
    return new class implements CanProvideConfig {
        public function get(string $path, mixed $default = null): mixed {
            if (func_num_args() > 1) {
                return $default;
            }
            throw new ConfigurationException("Key not found: {$path}");
        }

        public function has(string $path): bool {
            return false;
        }
    };
}

// Guards regression from instructor-3bd7 (cached defaults leaking across get() calls).
it('returns per-call default for same missing path', function () {
    $resolver = ConfigResolver::using(missingOnlyProvider());

    expect($resolver->get('missing.key', 'a'))->toBe('a');
    expect($resolver->get('missing.key', 'b'))->toBe('b');
});

it('keeps strict mode when missing path was previously read with default', function () {
    $resolver = ConfigResolver::using(missingOnlyProvider());
    $resolver->get('missing.key', 'a');

    expect(fn() => $resolver->get('missing.key'))
        ->toThrow(ConfigurationException::class, "No valid configuration found for path 'missing.key'");
});

