<?php

namespace Cognesy\Utils\Config\ConfigProviders;

use Cognesy\Utils\Config\Contracts\CanProvideConfig;
use Cognesy\Utils\Config\Exceptions\ConfigurationException;
use Cognesy\Utils\Deferred;

//use Cognesy\Utils\Events\EventDispatcher;
//use Cognesy\Utils\Events\Traits\HandlesEventDispatching;
//use Psr\EventDispatcher\EventDispatcherInterface;

class ConfigSource implements CanProvideConfig
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
            "Configuration resolution failed for preset '%s'.\n\nAttempts made:\n%s\n\nExceptions encountered:\n%s\n\nTo allow empty fallback configs (not recommended), call allowEmptyFallback() on the ConfigSource.",
            $preset,
            '  - ' . implode("\n  - ", $attempts),
            '  - ' . implode("\n  - ", $exceptionMessages)
        );

        throw new ConfigurationException($message);
    }
}

//class ConfigSource implements CanProvideConfig
//{
//    use HandlesEventDispatching;
//
//    /** @var Deferred[] $providers */
//    private array $providers = [];
//    private ?Deferred $fallback = null;
//    private ?Deferred $override = null;
//
//    public function __construct(
//        ?EventDispatcherInterface $events = null,
//    ) {
//        $this->events = $events ?? new EventDispatcher();
//    }
//
//    public function override(mixed $override) : static {
//        $this->override = match (true) {
//            is_null($override) => throw new \InvalidArgumentException('Override cannot be null.'),
//            is_callable($override) => new Deferred($override),
//            $override instanceof Deferred => $override,
//            is_object($override) => new Deferred(fn() => $override),
//            default => throw new \InvalidArgumentException('Override must be a callable or an object.'),
//        };
//        return $this;
//    }
//
//    public function fallbackTo(callable|Deferred|CanProvideConfig $provider) : static {
//        $this->fallback = match (true) {
//            is_null($provider) => throw new \InvalidArgumentException('Provider cannot be null.'),
//            is_callable($provider) => new Deferred($provider),
//            $provider instanceof Deferred => $provider,
//            $provider instanceof CanProvideConfig => new Deferred(fn() => $provider),
//            default => throw new \InvalidArgumentException('Provider must be a callable or an object.'),
//        };
//        return $this;
//    }
//
//    public function tryFrom(callable|Deferred|CanProvideConfig|null $provider) : static {
//        if ($provider !== null) {
//            array_unshift($this->providers, $this->makeDeferred($provider));
//        }
//        return $this;
//    }
//
//    public function thenFrom(callable|Deferred|CanProvideConfig|null $provider) : static {
//        if ($provider !== null) {
//            $this->providers[] = $this->makeDeferred($provider);
//        }
//        return $this;
//    }
//
//    public function getConfig(?string $preset = '') {
//        $exceptions = [];
//
//        // If an override is set, use it immediately
//        if ($this->override) {
//            try {
//                return $this->fromDeferred($this->override, $preset);
//            } catch (\Throwable $e) {
//                $exceptions[] = $e;
//            }
//        }
//
//        // Try each provider in order until one returns a non-null result
//        foreach ($this->providers as $provider) {
//            try {
//                $provider = $provider->resolve();
//                $result = $provider->getConfig($preset);
//            } catch (\Throwable $e) {
//                $exceptions[] = $e;
//                continue; // Skip this provider if it fails
//            }
//            if ($result !== null) {
//                return $result;
//            }
//        }
//
//        if ($this->fallback) {
//            try {
//                return $this->fromDeferred($this->fallback, $preset);
//            } catch (\Throwable $e) {
//                $exceptions[] = $e;
//            }
//        }
//
//        $this->throwError($preset, $exceptions);
//    }
//
//    // INTERNAL //////////////////////////////////////////////////////
//
//    private function fromDeferred(
//        Deferred $deferred,
//        $preset = '',
//        bool $throwOnNull = true
//    ) : mixed {
//        $object = $deferred->resolveUsing($preset);
//        return match (true) {
//            $object instanceof CanProvideConfig => $object->getConfig($preset),
//            !is_null($object) => $object,
//            default => match (true) {
//                !$throwOnNull => null,
//                default => throw new \InvalidArgumentException(
//                    'Configuration resolution using ' . get_class($object) . ' resulted in null'
//                ),
//            }
//        };
//    }
//
//    private function makeDeferred(callable|CanProvideConfig|Deferred $provider) : Deferred {
//        return match (true) {
//            is_null($provider) => throw new \InvalidArgumentException('Provider cannot be null.'),
//            is_callable($provider) => new Deferred($provider),
//            $provider instanceof Deferred => $provider,
//            $provider instanceof CanProvideConfig => new Deferred(fn() => $provider),
//            default => throw new \InvalidArgumentException('Provider must be a callable or an object.'),
//        };
//    }
//
//    protected function throwError(string $preset, array $exceptions) : void {
//        throw new \Exception('No configuration resolved for preset: '
//            . $preset
//            . ' and no fallback worked [' . $this->resolutionStackAsString() . ']. '
//            . 'Exceptions: '
//            . implode(', ', array_map(fn($e) => $e->getMessage(), $exceptions))
//        );
//    }
//
//    private function resolutionStackAsString() : string {
//        $stack = array_merge(
//            ['override: ' . ((string) ($this->override ?? '(none)'))],
//            ['providers: '],
//            $this->providers,
//            ['fallback: ' . ((string) ($this->fallback ?? '(no fallback)'))]
//        );
//        return implode(' -> ', array_map(
//            callback: fn(Deferred|string $d) => $d ? (string) $d : '(none)',
//            array: $stack
//        ));
//    }
//}