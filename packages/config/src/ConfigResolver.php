<?php declare(strict_types=1);

namespace Cognesy\Config;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Config\Providers\SettingsConfigProvider;
use Cognesy\Utils\Data\CachedMap;
use InvalidArgumentException;

class ConfigResolver implements CanProvideConfig
{
    /** @var array<callable(): CanProvideConfig> */
    private array $providerFactories;

    /** @var array<int, CanProvideConfig> */
    private array $resolvedProviders = [];
    private CachedMap $getCache;
    private CachedMap $hasCache;
    private bool $suppressProviderErrors;

    private function __construct(
        array $providers = [],
        bool $suppressProviderErrors = true
    ) {
        $this->providerFactories = array_map(
            fn($provider) => $this->createProviderFactory($provider),
            $providers
        );
        $this->suppressProviderErrors = $suppressProviderErrors;
        $this->getCache = new CachedMap(fn(string $path, $default) => $this->resolveGet($path, $default));
        $this->hasCache = new CachedMap(fn(string $path) => $this->resolveHas($path));
    }

    public static function default(): static {
        return (new static([new SettingsConfigProvider]));
    }

    public static function using(?CanProvideConfig $provider): static {
        return match(true) {
            is_null($provider) => self::default(),
            $provider instanceof ConfigResolver => $provider, // avoid double wrapping
            $provider instanceof CanProvideConfig => (new static([$provider, new SettingsConfigProvider])),
            default => throw new InvalidArgumentException('Provider must be a CanProvideConfig instance or null.'),
        };
    }

    public function then(callable|CanProvideConfig|null $provider): static {
        if ($provider !== null) {
            $newProviders = [...$this->providerFactories, $this->createProviderFactory($provider)];
            return new static($newProviders, $this->suppressProviderErrors);
        }
        return new static($this->providerFactories, $this->suppressProviderErrors);
    }

    public function withSuppressedProviderErrors(bool $suppress = true): static {
        $this->suppressProviderErrors = $suppress;
        return $this;
    }

    // MAIN API ///////////////////////////////////////////////////////////

    #[\Override]
    public function get(string $path, mixed $default = null): mixed {
        return $this->getCache->get($path, $default);
    }

    #[\Override]
    public function has(string $path): bool {
        return $this->hasCache->get($path);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function resolveGet(string $path, mixed $default): mixed {
        foreach (array_keys($this->providerFactories) as $index) {
            $value = $this->tryProviderGet($index, $path);
            if ($value !== null) {
                return $value;
            }
        }

        if ($default === null) {
            throw new ConfigurationException("No valid configuration found for path '{$path}'");
        }

        return $default;
    }

    private function resolveHas(string $path): bool {
        foreach (array_keys($this->providerFactories) as $index) {
            if ($this->tryProviderHas($index, $path)) {
                return true;
            }
        }
        return false;
    }

    private function createProviderFactory(mixed $provider): callable {
        return match (true) {
            is_null($provider) => throw new InvalidArgumentException('Provider cannot be null.'),
            $provider instanceof ConfigResolver => fn() => $provider,
            $provider instanceof CanProvideConfig => fn() => $provider,
            is_callable($provider) => $provider,
            default => throw new InvalidArgumentException('Provider must be callable or CanProvideConfig.'),
        };
    }

    private function getProvider(int $index): CanProvideConfig {
        if (!isset($this->resolvedProviders[$index])) {
            $provider = ($this->providerFactories[$index])();
            if (!$provider instanceof CanProvideConfig) {
                throw new ConfigurationException("Provider factory must return CanProvideConfig instance");
            }
            $this->resolvedProviders[$index] = $provider;
        }
        return $this->resolvedProviders[$index];
    }

    private function tryProviderGet(int $index, string $path): mixed {
        try {
            return $this->getProvider($index)->get($path);
        } catch (\Throwable $e) {
            if (!$this->suppressProviderErrors) {
                throw new ConfigurationException("Failed to resolve configuration from provider.", 0, $e);
            }
            return null;
        }
    }

    private function tryProviderHas(int $index, string $path): bool {
        try {
            return $this->getProvider($index)->has($path);
        } catch (\Throwable $e) {
            if (!$this->suppressProviderErrors) {
                throw new ConfigurationException("Failed to resolve configuration from provider.", 0, $e);
            }
            return false;
        }
    }
}
