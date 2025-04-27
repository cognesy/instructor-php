<?php
namespace Cognesy\Template\Script\Traits\ScriptParameters;

trait HandlesMutation
{
    public function set(string $name, mixed $value) : static {
        $this->parameters[$name] = $value;
        return $this;
    }

    public function unset(string $name) : static {
        unset($this->parameters[$name]);
        return $this;
    }
}