<?php declare(strict_types=1);

use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;

it('does not lose values when provider has() is weaker than get()', function () {
    $provider = new class implements CanProvideConfig {
        public function get(string $path, mixed $default = null): mixed {
            return match ($path) {
                'service.token' => 'abc123',
                default => $default,
            };
        }

        public function has(string $path): bool {
            return false;
        }
    };

    $resolver = ConfigResolver::using($provider);

    expect($resolver->get('service.token'))->toBe('abc123');
});
