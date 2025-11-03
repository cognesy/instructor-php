<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ToolCalling;

use Cognesy\Addons\Agent\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Data\AgentExecution;
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

    protected function toolExecutionMessages(AgentExecution $toolExecution) : Messages {
        $messages = Messages::empty();
        $messages = $messages->appendMessage($this->toolInvocationMessage($toolExecution->toolCall()));
        $messages = $messages->appendMessage($this->toolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result()));
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
        return new Message(
            role: 'tool',
            content: match(true) {
                is_string($value) => $value,
                is_array($value) => Json::encode($value),
                is_object($value) => Json::encode($value),
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value) || is_float($value) => (string) $value,
                default => var_export($value, true),
            },
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $value
            ]
        );
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

