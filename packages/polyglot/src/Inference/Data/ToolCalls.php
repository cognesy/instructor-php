<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

final readonly class ToolCalls
{
    /** @var ToolCall[] */
    private array $toolCalls;

    /** @param ToolCall[] $toolCalls */
    public function __construct(array $toolCalls = []) {
        $this->toolCalls = $toolCalls;
    }

    // CONSTRUCTORS ////////////////////////////////////////////////

    public static function empty() : ToolCalls {
        return new self();
    }

    public static function fromArray(array $toolCalls) : ToolCalls {
        $list = [];
        foreach ($toolCalls as $key => $toolCall) {
            $list[] = match(true) {
                is_array($toolCall) => ToolCall::fromArray($toolCall),
                is_object($toolCall) && $toolCall instanceof ToolCall => $toolCall,
                is_string($toolCall) => new ToolCall($key, self::makeArgs($toolCall)),
                default => throw new InvalidArgumentException('Cannot create ToolCall from provided data: ' . print_r($toolCall, true))
            };
        }
        return new ToolCalls($list);
    }

    public static function fromMapper(array $toolCalls, callable $mapper) : ToolCalls {
        $list = [];
        foreach ($toolCalls as $item) {
            $toolCall = $mapper($item);
            if ($toolCall instanceof ToolCall) {
                $list[] = $toolCall;
            }
        }
        return new ToolCalls($list);
    }

    // MUTATORS /////////////////////////////////////////////////////

    public function withAddedToolCall(string $name, array $args = []) : self {
        $newToolCall = new ToolCall(
            name: $name,
            args: $args,
        );
        return new self([...$this->toolCalls, $newToolCall]);
    }

    public function withLastToolCallUpdated(string $name, string $jsonString) : self {
        $argsArray = self::makeArgs($jsonString);
        if (empty($this->toolCalls)) {
            return $this->withAddedToolCall($name, $argsArray);
        }

        $updatedCalls = $this->toolCalls;
        $lastIndex = count($updatedCalls) - 1;
        $lastCall = $updatedCalls[$lastIndex];

        $updatedCall = $lastCall->withArgs($argsArray);
        if (empty($lastCall->name())) {
            $updatedCall = $updatedCall->withName($name);
        }

        $updatedCalls[$lastIndex] = $updatedCall;
        return new self($updatedCalls);
    }

    // ACCESSORS ////////////////////////////////////////////////////

    public function count() : int {
        return count($this->toolCalls);
    }

    public function first() : ?ToolCall {
        return $this->toolCalls[0] ?? null;
    }

    public function last() : ?ToolCall {
        if (empty($this->toolCalls)) {
            return null;
        }
        return $this->toolCalls[count($this->toolCalls) - 1];
    }

    public function hasSingle() : bool {
        return count($this->toolCalls) === 1;
    }

    public function hasMany() : bool {
        return count($this->toolCalls) > 1;
    }

    public function hasNone() : bool {
        return empty($this->toolCalls);
    }

    public function hasAny() : bool {
        return !empty($this->toolCalls);
    }

    public function isEmpty() : bool {
        return empty($this->toolCalls);
    }

    /** @return ToolCall[] */
    public function all() : array {
        return $this->toolCalls;
    }

    /** @return iterable<ToolCall> */
    public function each() : iterable {
        foreach ($this->toolCalls as $toolCall) {
            yield $toolCall;
        }
    }

    // TRANSFORMERS ////////////////////////////////////////////////

    public function map(callable $callback) : array {
        return array_map($callback, $this->toolCalls);
    }

    public function filter(callable $callback) : ToolCalls {
        return new ToolCalls(array_filter($this->toolCalls, $callback));
    }

    public function reduce(callable $callback, mixed $initial = null) : mixed {
        return array_reduce($this->toolCalls, $callback, $initial);
    }

    public function toArray() : array {
        $list = [];
        foreach ($this->toolCalls as $toolCall) {
            $list[] = $toolCall->toArray();
        }
        return $list;
    }

    public function toString() : string {
        $parts = [];
        foreach ($this->toolCalls as $toolCall) {
            $parts[] = $toolCall->toString();
        }
        return implode(" | ", $parts);
    }

    public function clone() : self {
        $clonedToolCalls = [];
        foreach ($this->toolCalls as $toolCall) {
            $clonedToolCalls[] = $toolCall->clone();
        }
        return new ToolCalls($clonedToolCalls);
    }

    // INTERNAL ////////////////////////////////////////////////////

    private static function makeArgs(string|array $args): array {
        return match(true) {
            is_array($args) => $args,
            is_string($args) => empty($args)
                ? []
                : Json::fromString($args)->toArray(),
            default => []
        };
    }
}