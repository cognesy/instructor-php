<?php

namespace Cognesy\Instructor\Configuration;

use InvalidArgumentException;

abstract class Configurator
{
    protected array $context;

    public static function with(array $context) : static {
        $instance = new static;
        $instance->context = $context;
        return $instance;
    }

    public static function addTo(Configuration $config) : void {
        (new static)->setup($config);
    }

    abstract public function setup(Configuration $config) : void;

    protected function context(string $name) : mixed {
        return $this->context[$name] ?? throw new InvalidArgumentException("Context value '$name' not found in configurator context for " . static::class);
    }
}
