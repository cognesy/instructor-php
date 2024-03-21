<?php
namespace Cognesy\Instructor\Configuration;

use Exception;
use function Cognesy\config\autowire;

class Configuration
{
    /** @var ComponentConfig[] array of component configurations */
    private array $config = [];
    /** @var object[] array of component instances */
    private array $instances = [];
    /** @var int[] uses to prevent dependency cycles */
    private array $trace = [];
    private bool $allowOverride = true; // does configuration allow override

    private static ?Configuration $instance = null;

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    // ACCESS

    /**
     * Get the singleton of empty configuration
     */
    static public function instance() : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = new Configuration();
        }
        return self::$instance;
    }

    /**
     * Get singleton of autowired configuration
     */
    static public function auto(array $overrides = []) : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = autowire(new Configuration())->override($overrides);
        }
        return self::$instance;
    }

    /**
     * Always new, autowired configuration; useful mostly for tests
     */
    static public function fresh(array $overrides = []) : Configuration {
        return autowire(new Configuration())->override($overrides);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    // USE

    /**
     * Get a component configuration for provided name (recommended: class or interface)
     */
    static public function for(string $name) : Configuration {
        return self::instance()->get($name);
    }

    /**
     * Does component exist
     */
    public function has(string $componentName) : mixed {
        return isset($this->config[$componentName]);
    }

    /**
     * Get a component instance
     */
    public function get(string $componentName) : mixed {
        return $this->resolveReference($componentName);
    }

    /**
     * Get a component instance
     */
    public function getConfig(string $componentName) : mixed {
        return $this->config[$componentName];
    }

    /**
     * Register a component with provided component configuration instance
     */
    public function register(ComponentConfig $componentConfig) : self {
        $componentName = $componentConfig->name;
        if (!$this->allowOverride && isset($this->config[$componentName])) {
            throw new Exception("Component $componentName already defined");
        }
        $this->config[$componentName] = $componentConfig;
        return $this;
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

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    // CONFIGURE

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
        if (is_null($name)) {
            $name = $class;
        }
        $this->register(new ComponentConfig($name, $class, $context, $getInstance, $injectContext));
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

    ///////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Resolve a component reference and return existing or fresh instance
     */
    private function resolveReference(string $componentName, bool $fresh = false) : mixed
    {
        if (!$this->has($componentName)) {
            throw new Exception('Component ' . $componentName . ' is not defined');
        }
        // if asked for fresh, return new component instance
        if ($fresh) {
            return $this->config[$componentName]->get();
        }
        // otherwise first check in instances
        if (isset($this->instances[$componentName])) {
            return $this->instances[$componentName];
        }
        $this->preventDependencyCycles($componentName);
        $this->instances[$componentName] = $this->config[$componentName]->get();
        return $this->instances[$componentName];
    }

    private function preventDependencyCycles(string $componentName) : void {
        if (!isset($this->trace[$componentName])) {
            $this->trace[$componentName] = count($this->trace) + 1;
        } else {
            $messages = [
                "Dependency cycle detected for [$componentName]",
                "TRACE:",
                print_r($this->trace, true),
                "CONFIG:",
                print_r($this->config, true),
            ];
            throw new Exception(implode('\n', $messages));
        }
    }
}
