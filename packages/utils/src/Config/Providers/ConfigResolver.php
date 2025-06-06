<?php

namespace Cognesy\Utils\Config\Providers;

use Cognesy\Utils\Config\Contracts\CanProvideConfig;
use Cognesy\Utils\Config\Exceptions\ConfigurationException;
use Cognesy\Utils\Deferred;

class ConfigResolver implements CanProvideConfig
{
    /** @var Deferred[] */
    private array $providers = [];
    private ?Deferred $fallback = null;
    private ?Deferred $override = null;
    private bool $allowEmptyFallback = false;

    public function override(mixed $override): static
    {
        $this->override = $this->createDeferred($override, 'Override cannot be null.');
        return $this;
    }

    public function fallbackTo(callable|Deferred|CanProvideConfig $provider): static
    {
        $this->fallback = $this->createDeferred($provider, 'Fallback provider cannot be null.');
        return $this;
    }

    /**
     * Allow fallback to empty config objects - use with caution!
     * This should only be used when you have explicit withXxx() methods
     * to populate required values later.
     */
    public function allowEmptyFallback(bool $allow = true): static
    {
        $this->allowEmptyFallback = $allow;
        return $this;
    }

    public function tryFrom(callable|Deferred|CanProvideConfig|null $provider): static
    {
        if ($provider !== null) {
            array_unshift($this->providers, $this->createDeferred($provider));
        }
        return $this;
    }

    public function thenFrom(callable|Deferred|CanProvideConfig|null $provider): static
    {
        if ($provider !== null) {
            $this->providers[] = $this->createDeferred($provider);
        }
        return $this;
    }

    public function getConfig(?string $preset = ''): mixed
    {
        $exceptions = [];
        $configurationAttempts = [];

        $config = $this->tryBuildConfig($preset, $exceptions, $configurationAttempts);
        if ($config == null) {
            $this->throwConfigurationError($preset ?? '', $exceptions, $configurationAttempts);
        }

        return $config;
    }

    // PRIVATE METHODS //////////////////////////////////////////////////////

    private function tryBuildConfig(?string $preset, array &$exceptions, array &$configurationAttempts): mixed{
        // Try override first
        if ($this->override) {
            $result = $this->tryResolve($this->override, $preset, $exceptions, $configurationAttempts, 'override');
            if ($result !== null) {
                return $result;
            }
        }

        // Try providers in order
        foreach ($this->providers as $index => $provider) {
            $result = $this->tryResolve($provider, $preset, $exceptions, $configurationAttempts, "provider[$index]");
            if ($result !== null) {
                return $result;
            }
        }

        // Try fallback
        if ($this->fallback) {
            $result = $this->tryResolve($this->fallback, $preset, $exceptions, $configurationAttempts, 'fallback');
            if ($result !== null) {
                return $result;
            }
        }

        // If empty fallback is allowed and we have a fallback that creates empty objects
        if ($this->allowEmptyFallback && $this->fallback) {
            try {
                $resolved = $this->resolveDeferredSafely($this->fallback, $preset);
                if ($resolved !== null) {
                    return $resolved;
                }
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }

        return null;
    }

    private function createDeferred(mixed $provider, string $nullErrorMessage = 'Provider cannot be null.'): Deferred
    {
        return match (true) {
            is_null($provider) => throw new \InvalidArgumentException($nullErrorMessage),
            is_callable($provider) => new Deferred($provider),
            $provider instanceof Deferred => $provider,
            $provider instanceof CanProvideConfig => new Deferred(fn() => $provider),
            is_object($provider) => new Deferred(fn() => $provider),
            default => throw new \InvalidArgumentException('Provider must be a callable, Deferred, or object.'),
        };
    }

    private function tryResolve(
        Deferred $deferred,
        ?string $preset,
        array &$exceptions,
        array &$attempts,
        string $source
    ): mixed {
        try {
            $resolved = $this->resolveDeferredSafely($deferred, $preset);

            if ($resolved !== null) {
                $attempts[] = "$source: succeeded";
                return $resolved;
            }

            $attempts[] = "$source: returned null";
            return null;
        } catch (\Throwable $e) {
            $exceptions[] = $e;
            $attempts[] = "$source: failed - " . $e->getMessage();
            return null;
        }
    }

    private function resolveDeferredSafely(Deferred $deferred, ?string $preset): mixed
    {
        $resolved = method_exists($deferred, 'resolveUsing')
            ? $deferred->resolveUsing($preset)
            : $deferred->resolve();

        return match (true) {
            $resolved instanceof CanProvideConfig => $resolved->getConfig($preset),
            $resolved !== null => $resolved,
            default => null,
        };
    }

    private function throwConfigurationError(string $preset, array $exceptions, array $attempts): never
    {
        $exceptionMessages = array_map(fn(\Throwable $e) => $e->getMessage(), $exceptions);

        $message = sprintf(
            "Configuration resolution failed for preset '%s'.\n\nAttempts made:\n%s\n\nExceptions encountered:\n%s\n\nTo allow empty fallback configs (not recommended), call allowEmptyFallback() on the ConfigResolver.",
            $preset,
            '  - ' . implode("\n  - ", $attempts),
            '  - ' . implode("\n  - ", $exceptionMessages)
        );

        throw new ConfigurationException($message);
    }
}
