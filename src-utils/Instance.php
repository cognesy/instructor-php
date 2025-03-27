<?php

namespace Cognesy\Utils;

use InvalidArgumentException;
use ReflectionClass;

/**
 * Utility for instantiation of given class with parameters
 */
class Instance
{
    private string $class;
    private array $params = [];

    public function __construct(string $class, array $params = [])
    {
        $this->class = $class;
        $this->params = $params;
    }

    public static function of(string $class): Instance
    {
        return new Instance($class);
    }

    public function withArgs(array $params): Instance
    {
        $this->params = $params;
        return $this;
    }

    public function make(): object
    {
        return $this->makeInstance($this->class, $this->params);
    }

    // INTERNAL ////////////////////////////////////////////////

    protected function makeInstance(string $class, array $params = []): object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class not found: $class");
        }

        $reflection = new ReflectionClass($class);

        // If there are no parameters, create instance directly
        if (empty($params)) {
            return new $class();
        }

        // If parameters are associative array, use named parameters
        if (array_keys($params) !== range(0, count($params) - 1)) {
            return $reflection->newInstance(...$params);
        }

        // For indexed array, pass parameters in order
        return $reflection->newInstanceArgs($params);
    }
}