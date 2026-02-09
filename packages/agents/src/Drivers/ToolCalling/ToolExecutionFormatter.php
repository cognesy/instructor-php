<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;

class ToolExecutionFormatter
{
    public function makeExecutionMessages(ToolExecutions $toolExecutions) : Messages {
        $messages = Messages::empty();
        foreach ($toolExecutions->all() as $toolExecution) {
            $messages = $messages->appendMessages($this->toolExecutionMessages($toolExecution));
        }
        return $messages;
    }

    protected function toolExecutionMessages(ToolExecution $toolExecution) : Messages {
        $messages = Messages::empty();
        $messages = $messages->appendMessage(
            $this->toolInvocationMessage($toolExecution->toolCall())
                ->withMetadata('tool_execution_id', $toolExecution->id())
        );
        $messages = $messages->appendMessage(
            $this->toolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result())
                ->withMetadata('tool_execution_id', $toolExecution->id())
        );
        return $messages;
    }

    protected function toolInvocationMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            content: '',
            metadata: [
                'tool_calls' => [$toolCall->toToolCallArray()]
            ]
        );
    }

    protected function toolExecutionResultMessage(ToolCall $toolCall, Result $result) : Message {
        return match(true) {
            $result instanceof Success => $this->toolExecutionSuccessMessage($toolCall, $result),
            $result instanceof Failure => $this->toolExecutionErrorMessage($toolCall, $result),
        };
    }

    protected function toolExecutionSuccessMessage(ToolCall $toolCall, Success $result) : Message {
        $value = $result->unwrap();
        $content = $this->formatResultContent($value);
        return new Message(
            role: 'tool',
            content: $content,
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $content,
            ]
        );
    }

    private function formatResultContent(mixed $value): string {
        return match(true) {
            is_string($value) => $value,
            $value instanceof \Stringable => $value->__toString(),
            is_array($value) => Json::encode($value),
            $value instanceof AgentState => $value->finalResponse()->toString(),
            is_object($value) => Json::encode($value),
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) || is_float($value) => (string) $value,
            default => var_export($value, true),
        };
    }

    protected function toolExecutionErrorMessage(ToolCall $toolCall, Failure $result) : Message {
        return new Message(
            role: 'tool',
            content: "Error in tool call: " . $result->errorMessage(),
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $result->error()
            ]
        );
    }
}
