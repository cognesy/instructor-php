<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanAcceptLLMProvider;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanAcceptAgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Hooks\Interceptors\CanInterceptAgentLifecycle;
use Cognesy\Http\HttpClient;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Json\Json;
use DateTimeImmutable;
use Override;

/**
 * ToolCallingDriver is responsible for managing the interaction between a
 * language model (LLM) and various tools. It defines the workflow for
 * generating a response based on input messages, selecting and invoking tools,
 * handling tool execution results, and crafting follow-up messages.
 */
class ToolCallingDriver implements CanUseTools, CanAcceptAgentEventEmitter, CanAcceptLLMProvider
{
    private LLMProvider $llm;
    private ?HttpClient $httpClient = null;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private OutputMode $mode;
    private array $options;
    private ?InferenceRetryPolicy $retryPolicy;
    private bool $parallelToolCalls = false;
    private ToolExecutionFormatter $formatter;
    private CanEmitAgentEvents $eventEmitter;

    public function __construct(
        ?LLMProvider $llm = null,
        ?HttpClient  $httpClient = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
        ?InferenceRetryPolicy $retryPolicy = null,
        ?CanEmitAgentEvents $eventEmitter = null,
        ?CanInterceptAgentLifecycle $interceptor = null,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
        $this->retryPolicy = $retryPolicy;
        $this->formatter = new ToolExecutionFormatter();
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
    }

    #[\Override]
    public function withLLMProvider(LLMProvider $llm): static {
        return new self(
            llm: $llm,
            httpClient: $this->httpClient,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            model: $this->model,
            options: $this->options,
            mode: $this->mode,
            retryPolicy: $this->retryPolicy,
            eventEmitter: $this->eventEmitter,
        );
    }

    #[\Override]
    public function llmProvider(): LLMProvider {
        return $this->llm;
    }

    public function withEventEmitter(CanEmitAgentEvents $eventEmitter): static {
        return new self(
            llm: $this->llm,
            httpClient: $this->httpClient,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            model: $this->model,
            options: $this->options,
            mode: $this->mode,
            retryPolicy: $this->retryPolicy,
            eventEmitter: $eventEmitter,
        );
    }

    /**
     * Executes tool usage within a given context and returns the result as a AgentStep.
     *
     * @param AgentState $state The context containing messages, tools, and other related information required for tool usage.
     * @return AgentState Returns an instance of AgentStep containing the response, executed tools, follow-up messages, and additional usage data.
     */
    #[Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor) : AgentState {
        // Get the tool call response from the LLM
        $response = $this->getToolCallResponse($state, $tools); // InferenceResponse
        $toolCalls = $response->toolCalls(); // ToolCalls

        // Execute each tool call
        $executions = $executor->executeTools($toolCalls, $state);

        // Build AgentStep from response and executions
        $messages = $this->formatter->makeExecutionMessages($executions);
        $context = $state->context()->messagesForInference();
        $step = $this->buildStepFromResponse(
            response: $response,
            executions: $executions,
            followUps: $messages,
            context: $context,
        );
        return $state->withCurrentStep($step);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getToolCallResponse(AgentState $state, Tools $tools) : InferenceResponse {
        $messages = $state->context()->messagesForInference();
        $cache = $state->context()->toInferenceContext($tools->toToolSchema());
        $cache = $cache->isEmpty() ? null : $cache;
        // Emit inference request started event
        $requestStartedAt = new DateTimeImmutable();
        $this->eventEmitter->inferenceRequestStarted($state, $messages->count(), $this->model ?: null);
        $response = $this->buildPendingInference($messages, $tools, $cache)->response();
        // Emit inference response received event
        $this->eventEmitter->inferenceResponseReceived($state, $response, $requestStartedAt);
        return $response;
    }

    /** Builds a PendingInference configured for tool-calling. */
    private function buildPendingInference(
        Messages $messages,
        Tools $tools,
        ?CachedInferenceContext $cache = null,
    ) : PendingInference {
        $toolChoice = is_array($this->toolChoice)
            ? ($this->toolChoice['type'] ?? 'auto')
            : $this->toolChoice;
        assert(is_string($toolChoice));

        $hasTools = !$tools->isEmpty();
        $hasCachedTools = ($cache?->tools() ?? []) !== [];
        $toolSchemas = ($hasTools && !$hasCachedTools) ? $tools->toToolSchema() : [];

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
        if ($this->retryPolicy !== null) {
            $inference = $inference->withRetryPolicy($this->retryPolicy);
        }
        if ($cache !== null) {
            $inference = $inference->withCachedContext(
                messages: $cache->messages()->toArray(),
                tools: $cache->tools(),
                toolChoice: $cache->toolChoice(),
                responseFormat: $cache->responseFormat()->toArray(),
            );
        }
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
        $outputMessages = $this->appendResponseContent($followUps, $response);
        return new AgentStep(
            inputMessages: $context,
            outputMessages: $outputMessages,
            inferenceResponse: $response,
            toolExecutions: $executions,
        );
    }

    private function appendResponseContent(Messages $messages, InferenceResponse $response) : Messages {
        $content = $response->content();
        if ($content === '') {
            return $messages;
        }
        if ($this->isToolArgsLeak($content, $response->toolCalls())) {
            return $messages;
        }
        return $messages->appendMessage(Message::asAssistant($content));
    }

    private function isToolArgsLeak(string $content, ToolCalls $toolCalls) : bool {
        if ($toolCalls->hasNone()) {
            return false;
        }
        $contentArgs = $this->parseContentArgs($content);
        if ($contentArgs === null) {
            return false;
        }
        foreach ($toolCalls->each() as $toolCall) {
            if ($toolCall->args() == $contentArgs) {
                return true;
            }
        }
        return false;
    }

    private function parseContentArgs(string $content) : ?array {
        $json = Json::fromString($content);
        if ($json->isEmpty()) {
            return null;
        }
        return $json->toArray();
    }


}
