<?php
namespace Cognesy\Instructor\Data;

class ToolCalls
{
    /**
     * @var ToolCall[]
     */
    private array $toolCalls = [];

    public function count() : int {
        return count($this->toolCalls);
    }

    public function last() : ToolCall {
        return $this->toolCalls[$this->count() - 1];
    }

    public function all() : array {
        return $this->toolCalls;
    }

    public function reset() : void {
        $this->toolCalls = [];
    }

    public function create(string $toolName, string $args = '') : ToolCall {
        $newToolCall = new ToolCall(
            name: $toolName,
            args: $args
        );
        $this->toolCalls[] = $newToolCall;
        return $newToolCall;
    }

    public function updateLast(string $responseJson, string $defaultName) : ToolCall {
        $last = $this->last();
        $last->name = $last->name ?? $defaultName;
        $last->args = $responseJson;
        return $this->last();
    }

    public function finalizeLast(string $responseJson, string $defaultName) : ToolCall {
        $last = $this->last();
        $last->name = $last->name ?? $defaultName;
        $last->args = $responseJson;
        return $this->last();
    }
}