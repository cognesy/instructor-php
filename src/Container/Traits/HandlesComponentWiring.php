<?php

namespace Cognesy\Instructor\Container\Traits;

use Cognesy\Instructor\Container\ComponentConfig;
use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Events\Container\ContainerReady;
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
    public function override(array $configOverrides, bool $excludeNulls = true) : Container {
        // ...filter out null / empty values
        if ($excludeNulls) {
            $configOverrides = array_filter($configOverrides);
        }
        // ...apply
        foreach ($configOverrides as $name => $value) {
            $this->object($name, getInstance: fn() => $value);
        }
        $this->events()->dispatch(new ContainerReady($configOverrides));
        return $this;
    }

    /**
     * Set context values
     */
    public function context(array $context) : self {
        foreach ($context as $name => $value) {
            $this->object($name, context: [$name => $value]);
        }
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