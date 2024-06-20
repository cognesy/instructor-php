<?php
namespace Cognesy\Instructor\Data\Messages\Traits\ScriptContext;

trait HandlesMutation
{
    public function set(string $name, mixed $value) : static {
        $this->context[$name] = $value;
        return $this;
    }

    public function unset(string $name) : static {
        unset($this->context[$name]);
        return $this;
    }
}