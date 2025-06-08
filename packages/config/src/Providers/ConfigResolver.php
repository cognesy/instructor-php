<?php

namespace Cognesy\Config\Providers;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Utils\Deferred;
use InvalidArgumentException;

class ConfigResolver implements CanProvideConfig
{
    /** @var Deferred[] */
    private array $providers = [];
    private ?Deferred $fallback = null;
    private ?Deferred $override = null;
    private bool $allowEmptyFallback = false;

    public static function default(): static {
        return (new static())->tryFrom(fn() => new SettingsConfigProvider());
    }

    public static function makeWith(?CanProvideConfig $provider): static {
        return (new static())
            ->tryFrom($provider)
            ->thenFrom(fn() => new SettingsConfigProvider());
    }

    public static function makeWithEmptyFallback(): static {
        return (new static())
            ->tryFrom(fn() => new SettingsConfigProvider())
            ->fallbackTo(fn() => [])
            ->allowEmptyFallback(true);
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

    public function getConfig(string $group, ?string $preset = ''): array {
        foreach ($this->makePipeline() as $provider) {
            $result = $this->tryResolve($provider, $group, $preset);
            if (!is_null($result)) {
                return $result;
            }
        }

        if (!$this->allowEmptyFallback) {
            // If empty fallback is allowed and we have a fallback that creates empty objects
            throw new ConfigurationException("No valid configuration found for group '{$group}' with preset '$preset'. ");
        }

        return [];
    }

    // INTERNAL ///////////////////////////////////////////////////////////

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

    private function tryResolve(Deferred $deferred, string $group, ?string $preset) : ?array {
        $resolved = $deferred->resolve();
        return match (true) {
            $resolved instanceof CanProvideConfig => $resolved->getConfig($group, $preset),
            $resolved !== null => $resolved,
            default => null,
        };
    }
}
