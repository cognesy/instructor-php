<?php

namespace Cognesy\Utils\Messages\Traits\ScriptParameters;

trait HandlesAccess
{
    public function has(string $name) : bool {
        return isset($this->parameters[$name]);
    }

    public function get(string $name, mixed $default = null) : mixed {
        return $this->parameters[$name] ?? $default;
    }

    public function isEmpty() : bool {
        return empty($this->parameters);
    }

    public function notEmpty() : bool {
        return !$this->isEmpty();
    }
}