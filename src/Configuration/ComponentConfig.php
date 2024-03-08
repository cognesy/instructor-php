<?php

namespace Cognesy\Instructor\Configuration;

use Cognesy\Instructor\Schema\Utils\ClassInfo;
use Exception;

class ComponentConfig {
    public function __construct(
        public string $name,
        public ?string $class = null,
        public array $context = [],
        /** @var callable */
        public $instanceCall = null,
        public bool $injectContext = false,
    ) {}

    public function get() : object {
        $context = $this->buildContext();
        $instance = match(true) {
            $this->instanceCall !== null => ($this->instanceCall)($context),
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