<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers;

use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Http\HttpClient;
use Cognesy\Addons\ToolUse\ToolExecution;
use Cognesy\Addons\ToolUse\ToolExecutions;
use Cognesy\Addons\ToolUse\ToolUseState;
use Cognesy\Addons\ToolUse\ToolUseStep;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Json\Json;
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
    private ?HttpClient $httpClient = null;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private OutputMode $mode;
    private array $options;
    private bool $parallelToolCalls = false;

    public function __construct(
        ?LLMProvider $llm = null,
        ?HttpClient   $httpClient = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;

        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
    }

    public function withHttpClient(HttpClient $httpClient) : self {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Executes tool usage within a given context and returns the result as a ToolUseStep.
     *
     * @param ToolUseState $state The context containing messages, tools, and other related information
     *                                required for tool usage.
     * @return ToolUseStep Returns an instance of ToolUseStep containing the response, executed tools,
     *                     follow-up messages, and additional usage data.
     */
    public function useTools(ToolUseState $state) : ToolUseStep {
        $messages = $state->messages();
        $tools = $state->tools();

        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            //->withDebugPreset('on')
            ->withMessages($messages->toArray())
            ->withModel($this->model)
            ->withTools($tools->toToolSchema())
            ->withToolChoice($this->toolChoice)
            ->withResponseFormat($this->responseFormat)
            ->withOptions(array_merge(
                $this->options,
                ['parallel_tool_calls' => $this->parallelToolCalls]
            ))
            ->withOutputMode($this->mode);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        $inferenceResponse = $inference->response();

        $toolExecutions = $tools->useTools($inferenceResponse->toolCalls(), $state);
        $followUpMessages = $this->makeFollowUpMessages($toolExecutions);

        return new ToolUseStep(
            response: $inferenceResponse->content(),
            toolCalls: $inferenceResponse->toolCalls(),
            toolExecutions: $toolExecutions,
            messages: $followUpMessages,
            usage: $inferenceResponse->usage(),
            inferenceResponse: $inferenceResponse,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * Generates and returns follow-up messages based on the provided tool executions.
     *
     * @param ToolExecutions $toolExecutions A collection of tool execution instances to process.
     * @return Messages A Messages object containing the generated follow-up messages.
     */
    protected function makeFollowUpMessages(ToolExecutions $toolExecutions) : Messages {
        $messages = Messages::empty();
        foreach ($toolExecutions->all() as $toolExecution) {
            $messages = $messages->appendMessages($this->makeToolExecutionMessages($toolExecution));
        }
        return $messages;
    }

    /**
     * Creates and returns messages corresponding to the execution of a single tool.
     *
     * @param ToolExecution $toolExecution An instance representing the tool execution to process.
     * @return Messages A Messages object containing the generated messages for the tool execution.
     */
    protected function makeToolExecutionMessages(ToolExecution $toolExecution) : Messages {
        $messages = Messages::empty();
        $messages = $messages->appendMessage($this->makeToolInvocationMessage($toolExecution->toolCall()));
        $messages = $messages->appendMessage($this->makeToolExecutionResultMessage($toolExecution->toolCall(), $toolExecution->result()));
        return $messages;
    }

    /**
     * Creates a tool invocation message based on the provided tool call object.
     *
     * @param ToolCall $toolCall The tool call object to be transformed into a message.
     * @return Message A message instance representing the tool invocation information.
     */
    protected function makeToolInvocationMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            content: '',
            metadata: [
                'tool_calls' => [$toolCall->toToolCallArray()]
            ]
        );
    }

    /**
     * Constructs a Message object based on the outcome of a tool execution.
     *
     * @param ToolCall $toolCall The tool call information used during the execution.
     * @param Result $result The result of the tool execution, which determines the type of message.
     * @return Message The constructed message representing the execution result.
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
     * @param Success $result The result of the tool execution, containing the successful outcome.
     * @return Message The constructed message containing the success details and metadata.
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
     * @return Message The constructed message encapsulating the error information.
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
