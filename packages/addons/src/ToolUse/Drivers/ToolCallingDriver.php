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
use Cognesy\Addons\ToolUse\Formatters\ToolExecutionFormatter;
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
    private ToolExecutionFormatter $formatter;

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
        $this->formatter = new ToolExecutionFormatter();
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
        $followUpMessages = $this->formatter->followUpMessages($toolExecutions);

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
    // INTERNAL /////////////////////////////////////////////////
}
