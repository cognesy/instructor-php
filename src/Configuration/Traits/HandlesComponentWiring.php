<?php

namespace Cognesy\Instructor\Configuration\Traits;

use Cognesy\Instructor\Configuration\ComponentConfig;
use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\Configuration\ConfigurationReady;
use Exception;

trait HandlesComponentWiring
{
    /** @var ComponentConfig[] array of component configurations */
    private array $config = [];
    private bool $allowOverride = true; // does configuration allow override

    /**
     * Does component exist
     */
    public function has(string $id) : bool {
        return !is_null($this->getConfig($id));
    }

    /**
     * Get a component instance
     */
    public function getConfig(string $componentName) : ?ComponentConfig {
        return $this->config[$componentName] ?? null;
    }

    /**
     * Set a component configuration
     */
    public function setConfig(string $componentName, ComponentConfig $componentConfig) : void {
        $this->config[$componentName] = $componentConfig;
    }

    /**
     * Apply configuration overrides; used for testing
     */
    public function override(array $configOverrides, bool $excludeNulls = true) : Configuration {
        // ...filter out null / empty values
        if ($excludeNulls) {
            $configOverrides = array_filter($configOverrides);
        }
        // ...apply
        foreach ($configOverrides as $name => $value) {
            $this->declare($name, getInstance: fn() => $value);
        }
        $this->events()->dispatch(new ConfigurationReady($configOverrides));
        return $this;
    }

    /**
     * Set context values
     */
    public function context(array $context) : self {
        foreach ($context as $name => $value) {
            $this->declare($name, context: [$name => $value]);
        }
        return $this;
    }

    /**
     * Declare a component configuration
     */
    public function declare(
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
     * Register a component with provided component configuration instance
     */
    public function register(ComponentConfig $componentConfig) : self {
        $componentName = $componentConfig->name;
        if (!$this->canOverride($componentName)) {
            throw new Exception("Component $componentName already defined");
        }
        $this->setConfig($componentName, $componentConfig);
        return $this;
    }

    public function canOverride(string $componentName): bool {
        return match(false) {
            is_null($this->getConfig($componentName)) => $this->allowOverride,
            default => true,
        };
    }

    public function getComponentNames() : array {
        return array_keys($this->config);
    }
}