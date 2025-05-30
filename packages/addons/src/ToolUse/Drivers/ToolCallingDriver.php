<?php

namespace Cognesy\Addons\ToolUse\Drivers;

use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\ToolExecution;
use Cognesy\Addons\ToolUse\ToolExecutions;
use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;
use Cognesy\Polyglot\LLM\Data\ToolCall;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\LLMProvider;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;

/**
 * ToolCallingDriver is responsible for managing the interaction between a
 * language model (LLM) and various tools. It defines the workflow for
 * generating a response based on input messages, selecting and invoking tools,
 * handling tool execution results, and crafting follow-up messages.
 */
class ToolCallingDriver implements CanUseTools
{
    private LLMProvider $llm;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private OutputMode $mode;
    private array $options;
    private bool $parallelToolCalls = false;

    public function __construct(
        ?LLMProvider $llm = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
    ) {
        $this->llm = $llm ?? new LLMProvider();

        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
    }

    /**
     * Executes tool usage within a given context and returns the result as a ToolUseStep.
     *
     * @param \Cognesy\Addons\ToolUse\ToolUseContext $context The context containing messages, tools, and other related information
     *                                required for tool usage.
     * @return ToolUseStep Returns an instance of ToolUseStep containing the response, executed tools,
     *                     follow-up messages, and additional usage data.
     */
    public function useTools(ToolUseContext $context) : ToolUseStep {
        $messages = $context->messages();
        $tools = $context->tools();

        $llmResponse = (new Inference)
            ->withLLMProvider($this->llm)
            ->with(
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
            )
            ->response();

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

    /**
     * Generates and returns follow-up messages based on the provided tool executions.
     *
     * @param \Cognesy\Addons\ToolUse\ToolExecutions $toolExecutions A collection of tool execution instances to process.
     * @return \Cognesy\Utils\Messages\Messages A Messages object containing the generated follow-up messages.
     */
    protected function makeFollowUpMessages(ToolExecutions $toolExecutions) : Messages {
        $messages = new Messages();
        foreach ($toolExecutions->all() as $toolExecution) {
            $messages->appendMessages($this->makeToolExecutionMessages($toolExecution));
        }
        return $messages;
    }

    /**
     * Creates and returns messages corresponding to the execution of a single tool.
     *
     * @param \Cognesy\Addons\ToolUse\ToolExecution $toolExecution An instance representing the tool execution to process.
     * @return \Cognesy\Utils\Messages\Messages A Messages object containing the generated messages for the tool execution.
     */
    protected function makeToolExecutionMessages(ToolExecution $toolExecution) : Messages {
        $messages = new Messages();
        $messages->appendMessage($this->makeToolInvocationMessage($toolExecution->toolCall()));
        $messages->appendMessage($this->makeToolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result()));
        return $messages;
    }

    /**
     * Creates a tool invocation message based on the provided tool call object.
     *
     * @param ToolCall $toolCall The tool call object to be transformed into a message.
     * @return \Cognesy\Utils\Messages\Message A message instance representing the tool invocation information.
     */
    protected function makeToolInvocationMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            metadata: [
                'tool_calls' => [$toolCall->toToolCallArray()]
            ]
        );
    }

    /**
     * Constructs a Message object based on the outcome of a tool execution.
     *
     * @param ToolCall $toolCall The tool call information used during the execution.
     * @param \Cognesy\Utils\Result\Result $result The result of the tool execution, which determines the type of message.
     * @return \Cognesy\Utils\Messages\Message The constructed message representing the execution result.
     */
    protected function makeToolExecutionResultMessage(ToolCall $toolCall, Result $result) : Message {
        return match(true) {
            $result instanceof Success => $this->makeToolExecutionSuccessMessage($toolCall, $result),
            $result instanceof Failure => $this->makeToolExecutionErrorMessage($toolCall, $result),
        };
    }

    /**
     * Creates a Message object to represent a successful execution of a tool.
     *
     * @param ToolCall $toolCall The tool call information used during the execution.
     * @param \Cognesy\Utils\Result\Success $result The result of the tool execution, containing the successful outcome.
     * @return \Cognesy\Utils\Messages\Message The constructed message containing the success details and metadata.
     */
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

    /**
     * Constructs a Message object to represent an error that occurred during a tool execution.
     *
     * @param ToolCall $toolCall The tool call information related to the execution.
     * @param Failure $result The failure result of the tool execution, containing the error details.
     * @return \Cognesy\Utils\Messages\Message The constructed message encapsulating the error information.
     */
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
