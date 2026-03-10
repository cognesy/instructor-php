<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling;

use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;
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
                ->withMetadata('tool_execution_id', $toolExecution->id()->toString())
        );
        $messages = $messages->appendMessage(
            $this->toolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result())
                ->withMetadata('tool_execution_id', $toolExecution->id()->toString())
        );
        return $messages;
    }

    protected function toolInvocationMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            content: '',
            toolCalls: new ToolCalls($toolCall),
        );
    }

    protected function toolExecutionResultMessage(ToolCall $toolCall, Result $result) : Message {
        return match(true) {
            $result instanceof Success => $this->toolExecutionSuccessMessage($toolCall, $result),
            $result instanceof Failure => $this->toolExecutionErrorMessage($toolCall, $result),
        };
    }

    protected function toolExecutionSuccessMessage(ToolCall $toolCall, Success $result) : Message {
        $content = $this->formatResultContent($result->unwrap());
        return new Message(
            role: 'tool',
            content: $content,
            toolResult: new ToolResult(
                content: $content,
                callId: $toolCall->id(),
                toolName: $toolCall->name(),
            ),
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
        $content = "Error in tool call: " . $result->errorMessage();
        return new Message(
            role: 'tool',
            content: $content,
            toolResult: new ToolResult(
                content: $content,
                callId: $toolCall->id(),
                toolName: $toolCall->name(),
                isError: true,
            ),
        );
    }
}
