<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use InvalidArgumentException;

class ToolCalls
{
    /** @var ToolCall[] */
    private array $toolCalls;

    /** @param ToolCall[] $toolCalls */
    public function __construct(array $toolCalls = []) {
        $this->toolCalls = $toolCalls;
    }

    public static function fromArray(array $toolCalls) : ToolCalls {
        $list = [];
        foreach ($toolCalls as $key => $toolCall) {
            $list[] = match(true) {
                is_array($toolCall) => ToolCall::fromArray($toolCall),
                is_object($toolCall) && $toolCall instanceof ToolCall => $toolCall,
                is_string($toolCall) => new ToolCall($key, $toolCall),
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

    public function empty() : bool {
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

    public function map(callable $callback) : array {
        return array_map($callback, $this->toolCalls);
    }

    public function filter(callable $callback) : ToolCalls {
        return new ToolCalls(array_filter($this->toolCalls, $callback));
    }

    public function reduce(callable $callback, mixed $initial = null) : mixed {
        return array_reduce($this->toolCalls, $callback, $initial);
    }

    public function reset() : void {
        $this->toolCalls = [];
    }

    public function add(string $toolName, string $args = '') : ToolCall {
        $newToolCall = new ToolCall(
            name: $toolName,
            args: $args
        );
        $this->toolCalls[] = $newToolCall;
        return $newToolCall;
    }

    public function updateLast(string $responseJson, string $defaultName) : ToolCall {
        $last = $this->last();
        if (empty($last)) {
            return $this->add($defaultName, $responseJson);
        }
        $last->withName($last->name() ?? $defaultName);
        $last->withArgs($responseJson);
        return $this->last();
    }

    public function finalizeLast(string $responseJson, string $defaultName) : ToolCall {
        return $this->updateLast($responseJson, $defaultName);
    }

    // IMMUTABLE METHODS (new) ////////////////////////////////////////////////

    /**
     * Returns a new ToolCalls instance with all tool calls removed.
     */
    public function withReset() : self {
        return new self([]);
    }

    /**
     * Returns a new ToolCalls instance with an additional tool call.
     */
    public function withAdded(string $name, string|array $args = '') : self {
        $newCall = new ToolCall($name, $args);
        return new self([...$this->toolCalls, $newCall]);
    }

    /**
     * Returns a new ToolCalls instance with the last tool call updated.
     * If no tool calls exist, adds a new one with the provided name and args.
     */
    public function withLastUpdated(string $responseJson, string $defaultName) : self {
        if (empty($this->toolCalls)) {
            return $this->withAdded($defaultName, $responseJson);
        }
        
        $calls = [...$this->toolCalls];
        $lastIndex = count($calls) - 1;
        $lastCall = $calls[$lastIndex];
        
        // Update args first, then name if needed
        $updatedCall = $lastCall->withArgs($responseJson);
        if (empty($lastCall->name())) {
            $updatedCall = $updatedCall->withName($defaultName);
        }
        
        $calls[$lastIndex] = $updatedCall;
        
        return new self($calls);
    }

    /**
     * Returns a new ToolCalls instance with the last tool call finalized.
     * Alias for withLastUpdated for backward compatibility.
     */
    public function withLastFinalized(string $responseJson, string $defaultName) : self {
        return $this->withLastUpdated($responseJson, $defaultName);
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
}