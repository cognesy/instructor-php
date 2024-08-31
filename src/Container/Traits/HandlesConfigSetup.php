<?php

namespace Cognesy\Instructor\Container\Traits;

use Cognesy\Instructor\Container\ComponentConfig;
use Exception;

trait HandlesConfigSetup
{
    /**
     * Declare a component configuration
     */
    public function object(
        string   $class,
        string   $name = null,
        array    $context = [],
        callable $getInstance = null,
        bool     $injectContext = true,
    ) : self {
        $name = $name ?? $class;
        $this->register((new ComponentConfig(
            $name, $class, $context, $getInstance, $injectContext
        ))->withEventDispatcher($this->events));
        return $this;
    }

    /**
     * Declare a value-type component
     */
    public function value(
        string $name,
        mixed $value,
    ) : self {
        $this->register((new ComponentConfig(
            name: $name,
            getInstance: match(true) {
                is_callable($value) => $value,
                default => fn() => $value,
            },
            injectContext: false
        ))->withEventDispatcher($this->events));
        return $this;
    }

    public function external(
        string $class,
        string $name = null,
        object $reference = null,
        array $context = [],
    ) : self {
        $this->register((new ComponentConfig(
            name: $name ?? $class,
            class: $class,
            context: $context,
            getInstance: fn() => $reference,
        ))->withEventDispatcher($this->events));
        return $this;
    }

    /**
     * Get a reference to a component
     */
    public function reference(string $componentName, bool $fresh = false) : callable {
        return function () use ($componentName, $fresh) {
            return $this->resolveReference($componentName, $fresh);
        };
    }

    /**
     * Get a list of references to components
     */
    public function referenceList(array $componentNames) : callable {
        return function () use ($componentNames) {
            $list = [];
            foreach ($componentNames as $componentName) {
                $list[] = $this->resolveReference($componentName);
            }
            return $list;
        };
    }
}
