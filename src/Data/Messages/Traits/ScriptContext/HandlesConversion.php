<?php

namespace Cognesy\Instructor\Data\Messages\Traits\ScriptContext;

trait HandlesConversion
{
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