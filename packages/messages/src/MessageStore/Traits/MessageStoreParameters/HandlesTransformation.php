<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStoreParameters;

use Cognesy\Messages\MessageStore\MessageStoreParameters;
use Exception;

trait HandlesTransformation
{
    public function filter(array $names, callable $condition) : MessageStoreParameters {
        return new MessageStoreParameters(array_filter(
            array: $this->parameters,
            callback: fn($name) => in_array($name, $names) && $condition($this->parameters[$name]),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function without(array $names) : MessageStoreParameters {
        return new MessageStoreParameters(array_filter(
            array: $this->parameters,
            callback: fn($name) => !in_array($name, $names),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function merge(array|MessageStoreParameters|null $parameters) : MessageStoreParameters {
        if (empty($parameters)) {
            return $this;
        }
        return match(true) {
            is_array($parameters) => new MessageStoreParameters(array_merge($this->parameters, $parameters)),
            $parameters instanceof MessageStoreParameters => $this->merge($parameters->parameters),
            default => throw new Exception("Invalid context type: " . gettype($parameters)),
        };
    }
}