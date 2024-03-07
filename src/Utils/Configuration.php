<?php
namespace Cognesy\Instructor\Utils;

use Exception;
use function Cognesy\config\autowire;

class Configuration
{
    /** @var ComponentConfig[] */
    public array $config = [];
    /** @var object[] */
    private array $instances = [];
    /** @var int[] */
    private array $trace = [];
    private bool $allowOverride = true;

    private static ?Configuration $instance = null;

    static public function instance() : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = new Configuration();
        }
        return self::$instance;
    }

    static public function for(string $name) : Configuration {
        return self::instance()->get($name);
    }

    static public function auto(array $overrides = []) : Configuration {
        if (is_null(self::$instance)) {
            self::$instance = autowire(new Configuration())->override($overrides);
        }
        return self::$instance;
    }

    static public function fresh(array $overrides = []) : Configuration {
        return autowire(new Configuration())->override($overrides);
    }

    public function reference(string $componentName) : callable {
        return function () use ($componentName) {
            return $this->resolveReference($componentName);
        };
    }

    public function declare(
        string $class,
        string $name = null,
        array $context = [],
        callable $instanceCall = null,
        bool $injectContext = true,
    ) : self {
        if (is_null($name)) {
            $name = $class;
        }
        $this->register(new ComponentConfig($name, $class, $context, $instanceCall, $injectContext));
        return $this;
    }

    public function register(ComponentConfig $componentConfig) : self {
        $componentName = $componentConfig->name;
        if (!$this->allowOverride && isset($this->config[$componentName])) {
            throw new Exception("Component $componentName already defined");
        }
        $this->config[$componentName] = $componentConfig;
        return $this;
    }

    public function get(string $componentName) : mixed {
        return $this->resolveReference($componentName);
    }

    public function context(array $context) : self {
        foreach ($context as $name => $value) {
            $this->declare($name, context: [$name => $value]);
        }
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
            $this->declare($name, instanceCall: fn() => $value);
        }
        return $this;
    }

    private function resolveReference(string $componentName) : mixed
    {
        if (!isset($this->config[$componentName])) {
            throw new Exception('Component ' . $componentName . ' is not defined');
        }
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
