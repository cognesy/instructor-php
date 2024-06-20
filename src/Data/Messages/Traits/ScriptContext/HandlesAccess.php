<?php

namespace Cognesy\Instructor\Data\Messages\Traits\ScriptContext;

trait HandlesAccess
{
    public function has(string $name) : bool {
        return isset($this->context[$name]);
    }

    public function get(string $name, mixed $default = null) : mixed {
        return $this->context[$name] ?? $default;
    }

    public function isEmpty() : bool {
        return empty($this->context);
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }
}