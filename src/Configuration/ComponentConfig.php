<?php

namespace Cognesy\Instructor\Configuration;

use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Exception;

/**
 * Class ComponentConfig
 *
 * @property string $name The name of the component, recommended to be the class or interface name
 * @property string|null $class The class name to instantiate - if provided
 * @property array $context The context values to be used to instantiate the component (name => value)
 * @property callable $getInstance Callback to create the instance (overrides provided class name), receives $context values
 * @property bool $injectContext Force attempt to set provided context values into the instance public properties (if they exist)
 */
class ComponentConfig {
    public function __construct(
        public string  $name,
        public ?string $class = null,
        public array   $context = [],
        /** @var callable */
        public         $getInstance = null,
        public bool    $injectContext = false,
    ) {}

    /**
     * Get the component instance
     */
    public function get() : object {
        $context = $this->buildContext();
        $instance = match(true) {
            $this->getInstance !== null => ($this->getInstance)($context),
            $this->class !== null => new $this->class(...$context),
            default => throw new Exception("Component $this->name has no class or factory defined"),
        };
        if (!empty($context) && ($this->injectContext === true)) {
            $instance = $this->injectContext($instance, $context);
        }
        if ($instance == null) {
            throw new Exception($this->name . " instance is null");
        }
        return $instance;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Set context values - e.g. execute registered callables
     */
    private function buildContext() : array {
        if (empty($this->context)) {
            return [];
        }
        $ctx = [];
        $contextItems = $this->context;
        foreach ($contextItems as $name => $value) {
            $ctx[$name] = match(true) {
                is_callable($value) => $value($this),
                default => $value,
            };
        }
        return $ctx;
    }

    /**
     * Inject context into a component instance
     * @param object $instance
     * @param array $context
     * @return object
     */
    private function injectContext(object $instance, array $context) : object {
        foreach ($context as $property => $value) {
            if (!property_exists($instance, $property)) {
                continue;
            }
            if (!((new ClassInfo)->isPublic(get_class($instance), $property))) {
                continue;
            }
            $instance->$property = $value;
        }
        return $instance;
    }
}