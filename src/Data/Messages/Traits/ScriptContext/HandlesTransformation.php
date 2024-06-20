<?php

namespace Cognesy\Instructor\Data\Messages\Traits\ScriptContext;

use Cognesy\Instructor\Data\Messages\ScriptContext;

trait HandlesTransformation
{
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
}