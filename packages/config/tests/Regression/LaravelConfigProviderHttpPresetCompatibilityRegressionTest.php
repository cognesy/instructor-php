<?php declare(strict_types=1);

use Cognesy\Config\ConfigPresets;
use Cognesy\Instructor\Laravel\Support\LaravelConfigProvider;
use Illuminate\Container\Container;

it('supports ConfigPresets http group contract via laravel bridge', function () {
    $config = new class {
        /** @var array<string,mixed> */
        private array $data = [
            'instructor' => [
                'http' => [
                    'driver' => 'curl',
                    'timeout' => 45,
                    'connect_timeout' => 7,
                ],
            ],
        ];

        public function get(string $path, mixed $default = null): mixed {
            $current = $this->data;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return $default;
                }
                $current = $current[$segment];
            }
            return $current;
        }

        public function has(string $path): bool {
            $current = $this->data;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return false;
                }
                $current = $current[$segment];
            }
            return true;
        }
    };

    $container = new Container();
    $container->instance('config', $config);

    $provider = new LaravelConfigProvider($container);
    $presets = ConfigPresets::using($provider)->for('http');
    $default = $presets->default();

    expect($default)->toBe([
        'driver' => 'curl',
        'requestTimeout' => 45,
        'connectTimeout' => 7,
    ]);
});
