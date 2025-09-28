<?php declare(strict_types=1);

namespace Cognesy\Config\Tests\Unit;

use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Config\Tests\TestCase;
use InvalidArgumentException;

class ConfigResolverTest extends TestCase
{
    private function createMockProvider(array $data = []): CanProvideConfig
    {
        return new class($data) implements CanProvideConfig {
            public function __construct(private array $data) {}

            public function get(string $path, mixed $default = null): mixed
            {
                $keys = explode('.', $path);
                $current = $this->data;

                foreach ($keys as $key) {
                    if (!isset($current[$key])) {
                        if ($default === null) {
                            throw new ConfigurationException("Key not found: {$path}");
                        }
                        return $default;
                    }
                    $current = $current[$key];
                }

                return $current;
            }

            public function has(string $path): bool
            {
                $keys = explode('.', $path);
                $current = $this->data;

                foreach ($keys as $key) {
                    if (!isset($current[$key])) {
                        return false;
                    }
                    $current = $current[$key];
                }

                return true;
            }
        };
    }

    public function test_default_creates_resolver_with_settings_provider(): void
    {
        $resolver = ConfigResolver::default();

        expect($resolver)->toBeInstanceOf(ConfigResolver::class);
    }

    public function test_using_null_returns_default_resolver(): void
    {
        $resolver = ConfigResolver::using(null);

        expect($resolver)->toBeInstanceOf(ConfigResolver::class);
    }

    public function test_using_config_resolver_returns_same_instance(): void
    {
        $originalResolver = ConfigResolver::default();
        $resolver = ConfigResolver::using($originalResolver);

        expect($resolver)->toBe($originalResolver);
    }

    public function test_using_config_provider_creates_new_resolver(): void
    {
        $provider = $this->createMockProvider(['test' => 'value']);
        $resolver = ConfigResolver::using($provider);

        expect($resolver)->toBeInstanceOf(ConfigResolver::class);
        expect($resolver->get('test'))->toBe('value');
    }

    public function test_using_invalid_provider_throws_exception(): void
    {
        $this->expectException(\TypeError::class);
        ConfigResolver::using('invalid');
    }

    public function test_then_adds_provider_to_chain(): void
    {
        $provider1 = $this->createMockProvider(['key1' => 'value1']);
        $provider2 = $this->createMockProvider(['key2' => 'value2']);

        $resolver = ConfigResolver::using($provider1)->then($provider2);

        expect($resolver->get('key1'))->toBe('value1');
        expect($resolver->get('key2'))->toBe('value2');
    }

    public function test_then_with_null_returns_copy(): void
    {
        $provider = $this->createMockProvider(['test' => 'value']);
        $resolver1 = ConfigResolver::using($provider);
        $resolver2 = $resolver1->then(null);

        expect($resolver2)->not->toBe($resolver1);
        expect($resolver2->get('test'))->toBe('value');
    }

    public function test_then_with_callable_factory(): void
    {
        $provider1 = $this->createMockProvider(['key1' => 'value1']);
        $factory = fn() => $this->createMockProvider(['key2' => 'value2']);

        $resolver = ConfigResolver::using($provider1)->then($factory);

        expect($resolver->get('key1'))->toBe('value1');
        expect($resolver->get('key2'))->toBe('value2');
    }

    public function test_provider_precedence_first_wins(): void
    {
        $provider1 = $this->createMockProvider(['key' => 'first']);
        $provider2 = $this->createMockProvider(['key' => 'second']);

        $resolver = ConfigResolver::using($provider1)->then($provider2);

        expect($resolver->get('key'))->toBe('first');
    }

    public function test_fallback_to_later_providers(): void
    {
        $provider1 = $this->createMockProvider(['key1' => 'value1']);
        $provider2 = $this->createMockProvider(['key2' => 'value2']);

        $resolver = ConfigResolver::using($provider1)->then($provider2);

        expect($resolver->get('key2'))->toBe('value2');
    }

    public function test_get_with_default_value(): void
    {
        $provider = $this->createMockProvider([]);
        $resolver = ConfigResolver::using($provider);

        expect($resolver->get('missing.key', 'default'))->toBe('default');
    }

    public function test_get_throws_when_no_providers_have_key(): void
    {
        $provider = $this->createMockProvider([]);
        $resolver = ConfigResolver::using($provider);

        expect(fn() => $resolver->get('missing.key'))
            ->toThrow(ConfigurationException::class, "No valid configuration found for path 'missing.key'");
    }

    public function test_has_returns_true_when_key_exists(): void
    {
        $provider = $this->createMockProvider(['existing' => 'value']);
        $resolver = ConfigResolver::using($provider);

        expect($resolver->has('existing'))->toBeTrue();
    }

    public function test_has_returns_false_when_key_missing(): void
    {
        $provider = $this->createMockProvider([]);
        $resolver = ConfigResolver::using($provider);

        expect($resolver->has('missing'))->toBeFalse();
    }

    public function test_has_checks_all_providers(): void
    {
        $provider1 = $this->createMockProvider(['key1' => 'value1']);
        $provider2 = $this->createMockProvider(['key2' => 'value2']);

        $resolver = ConfigResolver::using($provider1)->then($provider2);

        expect($resolver->has('key1'))->toBeTrue();
        expect($resolver->has('key2'))->toBeTrue();
        expect($resolver->has('missing'))->toBeFalse();
    }

    public function test_caching_prevents_multiple_resolutions(): void
    {
        $callCount = 0;
        $factory = function() use (&$callCount) {
            $callCount++;
            return $this->createMockProvider(['test' => 'value']);
        };

        $resolver = ConfigResolver::using(null)->then($factory);

        $resolver->get('test');
        $resolver->get('test');

        expect($callCount)->toBe(1);
    }

    public function test_with_suppressed_provider_errors(): void
    {
        $provider = new class implements CanProvideConfig {
            public function get(string $path, mixed $default = null): mixed {
                throw new \RuntimeException('Provider error');
            }

            public function has(string $path): bool {
                throw new \RuntimeException('Provider error');
            }
        };

        $resolver = ConfigResolver::using($provider)->withSuppressedProviderErrors(true);

        expect($resolver->get('any.key', 'default'))->toBe('default');
        expect($resolver->has('any.key'))->toBeFalse();
    }

    public function test_without_suppressed_provider_errors_throws(): void
    {
        $provider = new class implements CanProvideConfig {
            public function get(string $path, mixed $default = null): mixed {
                throw new \RuntimeException('Provider error');
            }

            public function has(string $path): bool {
                throw new \RuntimeException('Provider error');
            }
        };

        $resolver = ConfigResolver::using($provider)->withSuppressedProviderErrors(false);

        expect(fn() => $resolver->get('any.key', 'default'))
            ->toThrow(ConfigurationException::class, 'Failed to resolve configuration from provider.');

        expect(fn() => $resolver->has('any.key'))
            ->toThrow(ConfigurationException::class, 'Failed to resolve configuration from provider.');
    }

    public function test_provider_factory_validation(): void
    {
        $invalidFactory = fn() => 'not-a-provider';
        $resolver = ConfigResolver::using(null)->then($invalidFactory);

        expect(fn() => $resolver->get('any.key'))
            ->toThrow(ConfigurationException::class);
    }
}