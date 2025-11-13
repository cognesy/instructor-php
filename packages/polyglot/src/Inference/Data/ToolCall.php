<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Json\Json;

/**
 * Represents a tool call.
 */
final readonly class ToolCall
{
    private string $id;
    private string $name;
    private array $arguments;

    public function __construct(
        string $name,
        array $args = [],
        string $id = ''
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->arguments = $args;
    }

    // CONSTRUCTORS /////////////////////////////////////////////////

    public static function fromArray(array $toolCall) : ?ToolCall {
        if (empty($toolCall['name'])) {
            return null;
        }

        $args = match(true) {
            is_array($toolCall['arguments'] ?? false) => $toolCall['arguments'],
            is_string($toolCall['arguments'] ?? false) => Json::fromString($toolCall['arguments'])->toArray(),
            is_null($toolCall['arguments'] ?? null) => [],
            default => throw new \InvalidArgumentException('ToolCall args must be a string or an array')
        };

        return new ToolCall(
            name: $toolCall['name'] ?? '',
            args: $args,
            id: $toolCall['id'] ?? ''
        );
    }

    // MUTATORS ////////////////////////////////////////////////////

    public function with(
        ?string $id = null,
        ?string $name = null,
        ?array $args = null,
    ) : self {
        return new self(
            name: $name ?? $this->name,
            args: $args ?? $this->arguments,
            id: $id ?? $this->id,
        );
    }

    public function withId(string $id) : self {
        return $this->with(id: $id);
    }

    public function withName(string $name) : self {
        return $this->with(name: $name);
    }

    public function withArgs(string|array $args) : self {
        return $this->with(args: match(true) {
            is_array($args) => $args,
            is_string($args) => Json::fromString($args)->toArray(),
        });
    }

    // ACCESSORS ///////////////////////////////////////////////////

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

    // TRANSFORMERS ////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
            //
            'id' => $this->id,
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

    public function toString() : string {
        if (empty($this->arguments)) {
            return $this->name . "()";
        }
        
        $argStrings = [];
        foreach ($this->arguments as $key => $value) {
            $argStrings[] = $key . '=' . (is_string($value) ? $value : json_encode($value));
        }
        
        return $this->name . "(" . implode(', ', $argStrings) . ")";
    }
}
