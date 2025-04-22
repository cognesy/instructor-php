<?php
namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

class ToolCalls
{
    /**
     * @var ToolCall[]
     */
    private array $toolCalls;

    /**
     * @param ToolCall[] $toolCalls
     */
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

    /**
     * @return ToolCall[]
     */
    public function all() : array {
        return $this->toolCalls;
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

    public function toArray() : array {
        $list = [];
        foreach ($this->toolCalls as $toolCall) {
            $list[] = $toolCall->toArray();
        }
        return $list;
    }

    public function json() : Json {
        return Json::fromArray($this->toArray());
    }
}