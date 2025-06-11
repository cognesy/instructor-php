<?php

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Json\Json;

/**
 * Represents a tool call.
 */
class ToolCall
{
    private string $id;
    private string $name;
    private array $arguments;

    public function __construct(
        string $name,
        string|array $args = [],
        string $id = ''
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->withArgs($args);
    }

    public static function fromArray(array $toolCall) : ?ToolCall {
        if (empty($toolCall['name'])) {
            return null;
        }

        return new ToolCall(
            name: $toolCall['name'] ?? '',
            args: match(true) {
                is_array($toolCall['arguments'] ?? false) => $toolCall['arguments'],
                is_string($toolCall['arguments'] ?? false) => $toolCall['arguments'],
                is_null($toolCall['arguments'] ?? null) => [],
                default => throw new \InvalidArgumentException('ToolCall args must be a string or an array')
            },
            id: $toolCall['id'] ?? ''
        );
    }

    public function withId(string $id) : self {
        $this->id = $id;
        return $this;
    }

    public function withName(string $name) : self {
        $this->name = $name;
        return $this;
    }

    public function withArgs(string|array $args) : self {
        $this->arguments = match(true) {
            is_array($args) => $args,
            is_string($args) => Json::fromString($args)->toArray(),
            default => []
        };
        return $this;
    }

    public function hasArgs() : bool {
        return !empty($this->arguments);
    }

    public function id() : string {
        return $this->id;
    }

    public function name() : string {
        return $this->name;
    }

    public function args() : array {
        return $this->arguments;
    }

    public function argsAsJson() : string {
        return Json::encode($this->arguments);
    }

    public function hasValue(string $key) : bool {
        return isset($this->arguments[$key]);
    }

    public function value(string $key, mixed $default = null) : mixed {
        return $this->arguments[$key] ?? $default;
    }

    public function intValue(string $key, int $default = 0) : int {
        return (int) ($this->arguments[$key] ?? $default);
    }

    public function boolValue(string $key, bool $default = false) : bool {
        return (bool) ($this->arguments[$key] ?? $default);
    }

    public function stringValue(string $key, string $default = '') : string {
        return (string) ($this->arguments[$key] ?? $default);
    }

    public function arrayValue(string $key, array $default = []) : array {
        return (array) ($this->arguments[$key] ?? $default);
    }

    public function objectValue(string $key, ?object $default = null) : object {
        return (object) ($this->arguments[$key] ?? $default);
    }

    public function floatValue(string $key, float $default = 0.0) : float {
        return (float) ($this->arguments[$key] ?? $default);
    }

    public function toArray() : array {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    public function toToolCallArray() : array {
        return [
            'id' => $this->id,
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'arguments' => Json::encode($this->arguments),
            ]
        ];
    }

    public function clone() : self {
        return new self(
            name: $this->name,
            args: $this->arguments,
            id: $this->id
        );
    }
}
