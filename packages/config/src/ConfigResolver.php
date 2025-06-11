<?php
namespace Cognesy\Config;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Config\Providers\SettingsConfigProvider;
use Cognesy\Utils\Deferred;
use InvalidArgumentException;

class ConfigResolver implements CanProvideConfig
{
    /** @var Deferred[] */
    private array $providers = [];
    private array $cacheGet = [];
    private array $cacheHas = [];
    private bool $suppressProviderErrors = true;

    private function __construct(
        array $providers = [],
        bool $suppressProviderErrors = true
    ) {
        $this->providers = array_map(fn($provider) => $this->createDeferred($provider), $providers);
        $this->suppressProviderErrors = $suppressProviderErrors;
    }

    public static function default(): static {
        return (new static([new SettingsConfigProvider]));
    }

    public static function using(?CanProvideConfig $provider): static {
        return match(true) {
            is_null($provider) => self::default(),
            $provider instanceof ConfigResolver => $provider,
            $provider instanceof CanProvideConfig => (new static([$provider, new SettingsConfigProvider])),
            default => throw new InvalidArgumentException('Provider must be a CanProvideConfig instance or null.'),
        };
    }

    public function then(callable|Deferred|CanProvideConfig|null $provider): static {
        if ($provider !== null) {
            $this->providers[] = $this->createDeferred($provider);
        }
        return (new static($this->providers, $this->suppressProviderErrors));
    }

    public function withSuppressedProviderErrors(bool $suppress = true): static {
        $this->suppressProviderErrors = $suppress;
        return $this;
    }

    // MAIN API ///////////////////////////////////////////////////////////

    public function get(string $path, mixed $default = null): mixed {
        if (!isset($this->cacheGet[$path])) {
            $this->cacheGet[$path] = $this->resolveGet($path, $default);
        }
        return $this->cacheGet[$path];
    }

    public function has(string $path): bool {
        if (!isset($this->cacheHas[$path])) {
            $this->cacheHas[$path] = $this->resolveHas($path);
        }
        return $this->cacheHas[$path];
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private function resolveGet(string $path, mixed $default): mixed {
        foreach ($this->providers as $provider) {
            $value = $this->tryResolveGet($provider, $path);
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
        foreach ($this->providers as $provider) {
            if ($this->tryResolveHas($provider, $path)) {
                return true;
            }
        }
        return false;
    }

    private function createDeferred(mixed $provider): Deferred {
        return match (true) {
            is_null($provider) => throw new InvalidArgumentException('Provider cannot be null.'),
            is_callable($provider) => new Deferred($provider),
            $provider instanceof Deferred => $provider,
            $provider instanceof ConfigResolver => $provider,
            $provider instanceof CanProvideConfig => new Deferred(fn() => $provider),
            default => throw new InvalidArgumentException('Provider must be callable, Deferred, or CanProvideConfig.'),
        };
    }

    private function tryResolveGet(Deferred $deferred, string $path): mixed {
        try {
            $resolved = $deferred->resolve();
            if ($resolved instanceof CanProvideConfig) {
                return $resolved->get($path);
            }
        } catch (\Throwable $e) {
            if (!$this->suppressProviderErrors) {
                throw new ConfigurationException("Failed to resolve configuration from provider.", 0, $e);
            }
            // otherwise, ignore the error and continue to next provider
        }
        return null;
    }

    private function tryResolveHas(Deferred $deferred, string $path): bool {
        try {
            $resolved = $deferred->resolve();
            if ($resolved instanceof CanProvideConfig) {
                return $resolved->has($path);
            }
        } catch (\Throwable $e) {
            if (!$this->suppressProviderErrors) {
                throw new ConfigurationException("Failed to resolve configuration from provider.", 0, $e);
            }
            // otherwise, ignore the error and continue to next provider
        }
        return false;
    }
}
