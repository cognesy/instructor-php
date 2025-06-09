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
    private ?Deferred $fallback = null;
    private ?Deferred $override = null;
    private bool $allowEmptyFallback = false;
    private array $cacheGet = [];
    private array $cacheHas = [];

    public static function default(): static {
        return (new static)->tryFrom(fn() => new SettingsConfigProvider);
    }

    public static function makeWith(?CanProvideConfig $provider): static {
        return match(true) {
            is_null($provider) => self::default(),
            $provider instanceof ConfigResolver => $provider, // prevent double wrapping
            $provider instanceof CanProvideConfig => (new static)->tryFrom($provider)->thenFrom(fn() => new SettingsConfigProvider),
            default => throw new InvalidArgumentException('Provider must be a CanProvideConfig instance or null.'),
        };
    }

    /**
     * Allow fallback to empty config objects - use with caution!
     * This should only be used when you have explicit withXxx() methods
     * to populate required values later.
     */
    public function allowEmptyFallback(bool $allow = true): static {
        $this->allowEmptyFallback = $allow;
        return $this;
    }

    public function tryFrom(callable|Deferred|CanProvideConfig|null $provider): static {
        if ($provider !== null) {
            array_unshift($this->providers, $this->createDeferred($provider));
        }
        return $this;
    }

    public function thenFrom(callable|Deferred|CanProvideConfig|null $provider): static {
        if ($provider !== null) {
            $this->providers[] = $this->createDeferred($provider);
        }
        return $this;
    }

    public function override(mixed $override): static {
        $this->override = $this->createDeferred($override, 'Override cannot be null.');
        return $this;
    }

    public function fallbackTo(callable|Deferred|CanProvideConfig $provider): static {
        $this->fallback = $this->createDeferred($provider, 'Fallback provider cannot be null.');
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

    private function resolveGet(string $path, mixed $default) : mixed {
        foreach ($this->makePipeline() as $provider) {
            $result = $this->tryResolveGet($provider, $path);
            if (!is_null($result)) {
                return $result;
            }
        }
        if (!$this->allowEmptyFallback && is_null($default)) {
            // if empty fallback is allowed and we have a fallback that creates empty objects
            throw new ConfigurationException("No valid configuration found for path '{$path}'");
        }
        return $default;
    }

    private function resolveHas(string $path) : bool {
        foreach ($this->makePipeline() as $provider) {
            $result = $this->tryResolveHas($provider, $path);
            if (!is_null($result) && $result === true) {
                return $result;
            }
        }
        return false;
    }

    private function makePipeline() : array {
        $pipeline = [];
        if ($this->override) { $pipeline[] = $this->override; }
        foreach ($this->providers as $provider) {
            $pipeline[] = $provider;
        }
        if ($this->fallback) { $pipeline[] = $this->fallback; }
        return $pipeline;
    }

    private function createDeferred(mixed $provider) : Deferred {
        return match (true) {
            is_null($provider) => throw new InvalidArgumentException('Provider cannot be null.'),
            is_callable($provider) => new Deferred($provider),
            $provider instanceof Deferred => $provider,
            $provider instanceof CanProvideConfig => new Deferred(fn() => $provider),
            is_object($provider) => new Deferred(fn() => $provider),
            default => throw new InvalidArgumentException('Provider must be a callable, Deferred, or object.'),
        };
    }

    private function tryResolveGet(Deferred $deferred, string $path) : mixed {
        $resolved = $deferred->resolve();
        return match (true) {
            $resolved instanceof CanProvideConfig => $resolved->get($path),
            $resolved !== null => $resolved,
            default => null,
        };
    }

    private function tryResolveHas(Deferred $deferred, string $path) : ?bool {
        $resolved = $deferred->resolve();
        return match (true) {
            $resolved instanceof CanProvideConfig => $resolved->has($path),
            $resolved !== null => $resolved,
            default => null,
        };
    }
}
