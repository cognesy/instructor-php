<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ToolCalling;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
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
            $messages = $messages->appendMessage($this->toolExecutionResultMessage(
                $toolExecution->toolCall(),
                $toolExecution->result(),
            ));
        }
        return $messages;
    }

    protected function toolExecutionResultMessage(ToolCall $toolCall, Result $result) : Message {
        return match(true) {
            $result instanceof Success => $this->toolExecutionSuccessMessage($toolCall, $result),
            $result instanceof Failure => $this->toolExecutionErrorMessage($toolCall, $result),
        };
    }

    protected function toolExecutionSuccessMessage(ToolCall $toolCall, Success $result) : Message {
        $value = $result->unwrap();
        $content = match(true) {
            is_string($value) => $value,
            is_array($value) => Json::encode($value),
            is_object($value) => Json::encode($value),
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) || is_float($value) => (string) $value,
            default => var_export($value, true),
        };
        return new Message(
            role: 'tool',
            parts: new ContentParts(ContentPart::toolResult(new ToolResult(
                content: $content,
                callId: $toolCall->id(),
                toolName: $toolCall->name(),
            ))),
        );
    }

    protected function toolExecutionErrorMessage(ToolCall $toolCall, Failure $result) : Message {
        $content = "Error in tool call: " . $result->errorMessage();
        return new Message(
            role: 'tool',
            parts: new ContentParts(ContentPart::toolResult(new ToolResult(
                content: $content,
                callId: $toolCall->id(),
                toolName: $toolCall->name(),
                isError: true,
            ))),
        );
    }
}
