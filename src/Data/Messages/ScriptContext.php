<?php

namespace Cognesy\Instructor\Data\Messages;

use Exception;

class ScriptContext
{
    private array $context;

    public function __construct(?array $context) {
        $this->context = $context ?? [];
    }

    public function filter(array $names, callable $condition) : ScriptContext {
        return new ScriptContext(array_filter(
            array: $this->context,
            callback: fn($name) => in_array($name, $names) && $condition($this->context[$name]),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function without(array $names) : ScriptContext {
        return new ScriptContext(array_filter(
            array: $this->context,
            callback: fn($name) => !in_array($name, $names),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function merge(array|ScriptContext|null $context) : ScriptContext {
        if (empty($context)) {
            return $this;
        }
        return match(true) {
            is_array($context) => new ScriptContext(array_merge($this->context, $context)),
            $context instanceof ScriptContext => $this->merge($context->context),
            default => throw new Exception("Invalid context type: " . gettype($context)),
        };
    }

    public function has(string $name) : bool {
        return isset($this->context[$name]);
    }

    public function get(string $name, mixed $default = null) : mixed {
        return $this->context[$name] ?? $default;
    }

    public function set(string $name, mixed $value) : static {
        $this->context[$name] = $value;
        return $this;
    }

    public function unset(string $name) : static {
        unset($this->context[$name]);
        return $this;
    }

    public function toArray() : array {
        return $this->context;
    }

    public function keys() : array {
        return array_keys($this->context);
    }

    public function values() : array {
        return array_values($this->context);
    }
}