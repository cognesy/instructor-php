<?php declare(strict_types=1);
namespace Cognesy\Template\Script\Traits\ScriptParameters;

trait HandlesMutation
{
    public function set(string $name, mixed $value) : static {
        $newParameters = $this->parameters;
        $newParameters[$name] = $value;
        return new static($newParameters);
    }

    public function unset(string $name) : static {
        $newParameters = $this->parameters;
        unset($newParameters[$name]);
        return new static($newParameters);
    }

}