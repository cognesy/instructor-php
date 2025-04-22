<?php
namespace Cognesy\Utils\Messages\Traits\Script;

use Cognesy\Utils\Messages\ScriptParameters;

trait HandlesParameters
{
    private ?ScriptParameters $parameters = null;

    public function parameters() : ScriptParameters {
        return $this->parameters;
    }

    public function withParams(array|ScriptParameters $parameters) : static {
        $this->parameters = match(true) {
            $parameters instanceof ScriptParameters => $parameters,
            default => new ScriptParameters($parameters),
        };
        return $this;
    }

    public function setParameter(string $name, mixed $value) : static {
        $this->parameters->set($name, $value);
        return $this;
    }

    public function unsetParameter(string $name) : static {
        $this->parameters->unset($name);
        return $this;
    }
}
