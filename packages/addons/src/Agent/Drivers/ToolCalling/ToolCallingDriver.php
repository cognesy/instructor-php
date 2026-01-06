<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ToolCalling;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Core\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Enums\AgentStepType;
use Cognesy\Http\HttpClient;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;

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
        ?HttpClient  $httpClient = null,
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

    public function withLLMProvider(LLMProvider $llm): self {
        $clone = clone $this;
        $clone->llm = $llm;
        return $clone;
    }

    public function getLlmProvider(): LLMProvider {
        return $this->llm;
    }

    /**
     * Executes tool usage within a given context and returns the result as a AgentStep.
     *
     * @param AgentState $state The context containing messages, tools, and other related information required for tool usage.
     * @return AgentStep Returns an instance of AgentStep containing the response, executed tools, follow-up messages, and additional usage data.
     */
    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor) : AgentStep {
        $response = $this->getToolCallResponse($state, $tools);
        $toolCalls = $this->getToolsToCall($response);
        $executions = $executor->useTools($toolCalls, $state);
        $messages = $this->formatter->makeExecutionMessages($executions);
        return $this->buildStepFromResponse(
            response: $response,
            executions: $executions,
            followUps: $messages,
            context: $state->messages(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getToolCallResponse(AgentState $state, Tools $tools) : InferenceResponse {
        return $this->buildPendingInference($state->messages(), $tools)->response();
    }

    private function getToolsToCall(InferenceResponse $response): ToolCalls {
        return $response->toolCalls();
    }

    /** Builds a PendingInference configured for tool-calling. */
    private function buildPendingInference(
        Messages $messages,
        Tools $tools
    ) : PendingInference {
        $toolChoice = is_array($this->toolChoice)
            ? ($this->toolChoice['type'] ?? 'auto')
            : $this->toolChoice;
        assert(is_string($toolChoice));

        $hasTools = !$tools->isEmpty();
        $toolSchemas = $hasTools ? $tools->toToolSchema() : [];

        // Only include parallel_tool_calls when tools are specified (API requirement)
        $options = $this->options;
        if ($hasTools) {
            $options = array_merge($options, ['parallel_tool_calls' => $this->parallelToolCalls]);
        }

        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            ->withMessages($messages->toArray())
            ->withModel($this->model)
            ->withTools($toolSchemas)
            ->withToolChoice($hasTools ? $toolChoice : '')
            ->withResponseFormat($this->responseFormat)
            ->withOptions($options)
            ->withOutputMode($this->mode);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference->create();
    }

    /** Builds the final AgentStep from inference response and executions. */
    private function buildStepFromResponse(
        InferenceResponse $response,
        ToolExecutions $executions,
        Messages $followUps,
        Messages $context,
    ) : AgentStep {
        $outputMessages = $followUps->appendMessage(
            Message::asAssistant($response->content()),
        );

        return new AgentStep(
            inputMessages: $context,
            outputMessages: $outputMessages,
            usage: $response->usage(),
            toolCalls: $response->toolCalls(),
            toolExecutions: $executions,
            inferenceResponse: $response,
            stepType: $this->inferStepType($response, $executions)
        );
    }

    private function inferStepType(InferenceResponse $response, ToolExecutions $executions) : AgentStepType {
        return match (true) {
            $executions->hasErrors() => AgentStepType::Error,
            $response->hasToolCalls() => AgentStepType::ToolExecution,
            default => AgentStepType::FinalResponse,
        };
    }
}
