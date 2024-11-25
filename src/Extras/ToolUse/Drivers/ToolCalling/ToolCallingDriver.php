<?php

namespace Cognesy\Instructor\Extras\ToolUse\Drivers\ToolCalling;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\ToolUse\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\ToolUse\ToolExecution;
use Cognesy\Instructor\Extras\ToolUse\ToolExecutions;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Result\Failure;
use Cognesy\Instructor\Utils\Result\Result;
use Cognesy\Instructor\Utils\Result\Success;

class ToolCallingDriver implements CanUseTools
{
    private Inference $inference;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private Mode $mode;
    private array $options;
    private bool $parallelToolCalls = false;

    public function __construct(
        Inference    $inference = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        Mode         $mode = Mode::Tools,
    ) {
        $this->inference = $inference ?? new Inference();

        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
    }

    public function useTools(ToolUseContext $context) : ToolUseStep {
        $messages = $context->messages();
        $tools = $context->tools();

        $llmResponse = $this->inference->create(
            messages: $messages->toArray(),
            model: $this->model,
            tools: $tools->toToolSchema(),
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            options: array_merge(
                $this->options,
                ['parallel_tool_calls' => $this->parallelToolCalls]
            ),
            mode: $this->mode,
        )->response();

        $toolExecutions = $tools->useTools($llmResponse->toolCalls(), $context);
        $followUpMessages = $this->makeFollowUpMessages($toolExecutions);

        return new ToolUseStep(
            response: $llmResponse->content(),
            toolCalls: $llmResponse->toolCalls(),
            toolExecutions: $toolExecutions,
            messages: $followUpMessages,
            usage: $llmResponse->usage(),
            llmResponse: $llmResponse,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    protected function makeFollowUpMessages(ToolExecutions $toolExecutions) : Messages {
        $messages = new Messages();
        foreach ($toolExecutions->all() as $toolExecution) {
            $messages->appendMessages($this->makeToolExecutionMessages($toolExecution));
        }
        return $messages;
    }

    protected function makeToolExecutionMessages(ToolExecution $toolExecution) : Messages {
        $messages = new Messages();
        $messages->appendMessage($this->makeToolInvocationMessage($toolExecution->toolCall()));
        $messages->appendMessage($this->makeToolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result()));
        return $messages;
    }

    protected function makeToolInvocationMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            metadata: [
                'tool_calls' => [$toolCall->toToolCallArray()]
            ]
        );
    }

    protected function makeToolExecutionResultMessage(ToolCall $toolCall, Result $result) : Message {
        return match(true) {
            $result instanceof Success => $this->makeToolExecutionSuccessMessage($toolCall, $result),
            $result instanceof Failure => $this->makeToolExecutionErrorMessage($toolCall, $result),
        };
    }

    protected function makeToolExecutionSuccessMessage(ToolCall $toolCall, Success $result) : Message {
        $value = $result->unwrap();
        return new Message(
            role: 'tool',
            content: match(true) {
                is_string($value) => $value,
                is_array($value) => Json::encode($value),
                is_object($value) => Json::encode($value),
                default => (string) $value,
            },
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $value
            ]
        );
    }

    protected function makeToolExecutionErrorMessage(ToolCall $toolCall, Failure $result) : Message {
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
