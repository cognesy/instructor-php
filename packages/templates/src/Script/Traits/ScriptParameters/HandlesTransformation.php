<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\ScriptParameters;

use Cognesy\Template\Script\ScriptParameters;
use Exception;

trait HandlesTransformation
{
    public function filter(array $names, callable $condition) : ScriptParameters {
        return new ScriptParameters(array_filter(
            array: $this->parameters,
            callback: fn($name) => in_array($name, $names) && $condition($this->parameters[$name]),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function without(array $names) : ScriptParameters {
        return new ScriptParameters(array_filter(
            array: $this->parameters,
            callback: fn($name) => !in_array($name, $names),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function merge(array|ScriptParameters|null $parameters) : ScriptParameters {
        if (empty($parameters)) {
            return $this;
        }
        return match(true) {
            is_array($parameters) => new ScriptParameters(array_merge($this->parameters, $parameters)),
            $parameters instanceof ScriptParameters => $this->merge($parameters->parameters),
            default => throw new Exception("Invalid context type: " . gettype($parameters)),
        };
    }
}