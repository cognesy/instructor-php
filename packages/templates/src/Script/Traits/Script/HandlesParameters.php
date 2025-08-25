<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Template\Script\ScriptParameters;

trait HandlesParameters
{
    public function parameters() : ScriptParameters {
        return $this->parameters;
    }

    public function withParams(array|ScriptParameters $parameters) : static {
        $newParameters = match(true) {
            $parameters instanceof ScriptParameters => $parameters,
            default => new ScriptParameters($parameters),
        };
        return new static(
            sections: $this->sections,
            parameters: $newParameters,
        );
    }

    public function setParameter(string $name, mixed $value) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->set($name, $value),
        );
    }

    public function unsetParameter(string $name) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->unset($name),
        );
    }
}
