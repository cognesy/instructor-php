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
        $this->getCache = new CachedMap(fn(string $path) => $this->resolveGet($path));
        $this->hasCache = new CachedMap(fn(string $path) => $this->resolveHas($path));
    }

    public static function default(): static {
        return (new static([new SettingsConfigProvider]));
    }

    public static function using(?CanProvideConfig $provider): self {
        return match(true) {
            is_null($provider) => self::default(),
            $provider instanceof ConfigResolver => $provider, // avoid double wrapping
            $provider instanceof CanProvideConfig => (new self([$provider, new SettingsConfigProvider])),
            default => throw new InvalidArgumentException('Provider must be a CanProvideConfig instance or null.'),
        };
    }

    /**
     * @param (callable(): CanProvideConfig)|CanProvideConfig|null $provider
     */
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
        $resolved = $this->getCache->get($path);
        if ($resolved['found']) {
            return $resolved['value'];
        }
        if (func_num_args() > 1) {
            return $default;
        }
        throw new ConfigurationException("No valid configuration found for path '{$path}'");
    }

    #[\Override]
    public function has(string $path): bool {
        return $this->hasCache->get($path);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /** @return array{found:bool,value:mixed} */
    private function resolveGet(string $path): array {
        foreach (array_keys($this->providerFactories) as $index) {
            $resolved = $this->tryProviderGet($index, $path);
            if ($resolved['found']) {
                return $resolved;
            }
        }
        return ['found' => false, 'value' => null];
    }

    private function resolveHas(string $path): bool {
        foreach (array_keys($this->providerFactories) as $index) {
            if ($this->tryProviderHas($index, $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return callable(): CanProvideConfig
     */
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

    /** @return array{found:bool,value:mixed} */
    private function tryProviderGet(int $index, string $path): array {
        try {
            $provider = $this->getProvider($index);
            if (!$provider->has($path)) {
                return ['found' => false, 'value' => null];
            }
            return ['found' => true, 'value' => $provider->get($path)];
        } catch (\Throwable $e) {
            if (!$this->suppressProviderErrors) {
                throw new ConfigurationException("Failed to resolve configuration from provider.", 0, $e);
            }
            return ['found' => false, 'value' => null];
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
