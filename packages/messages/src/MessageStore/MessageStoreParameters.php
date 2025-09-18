<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use RuntimeException;

final readonly class MessageStoreParameters
{
    public function __construct(
        private array $parameters = [],
    ) {}

    // CONSTRUCTORS /////////////////////////////////////////////

    // ACCESSORS ////////////////////////////////////////////////

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

    public function keys() : array {
        return array_keys($this->parameters);
    }

    public function values() : array {
        return array_values($this->parameters);
    }

    // MUTATORS /////////////////////////////////////////////////

    public function set(string $name, mixed $value) : MessageStoreParameters {
        $newParameters = $this->parameters;
        $newParameters[$name] = $value;
        return new static($newParameters);
    }

    public function unset(string $name) : MessageStoreParameters {
        $newParameters = $this->parameters;
        unset($newParameters[$name]);
        return new static($newParameters);
    }

    // CONVERSIONS and TRANSFORMATIONS //////////////////////////

    public function toArray() : array {
        return $this->parameters;
    }

    public function filter(array $names, callable $condition) : MessageStoreParameters {
        return new MessageStoreParameters(array_filter(
            array: $this->parameters,
            callback: fn($name) => in_array($name, $names, true) && $condition($this->parameters[$name]),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function without(array $names) : MessageStoreParameters {
        return new MessageStoreParameters(array_filter(
            array: $this->parameters,
            callback: fn($name) => !in_array($name, $names, true),
            mode: ARRAY_FILTER_USE_KEY
        ));
    }

    public function merge(array|MessageStoreParameters|null $parameters) : MessageStoreParameters {
        if (empty($parameters)) {
            return $this;
        }
        return match(true) {
            is_array($parameters) => new MessageStoreParameters(array_merge($this->parameters, $parameters)),
            $parameters instanceof self => $this->merge($parameters->parameters),
            default => throw new RuntimeException("Invalid context type: " . gettype($parameters)),
        };
    }
}
