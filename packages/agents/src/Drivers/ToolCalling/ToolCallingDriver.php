<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling;

use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\HttpClient;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\InferenceRuntime;
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
class ToolCallingDriver implements CanUseTools, CanAcceptLLMConfig, CanAcceptMessageCompiler
{
    private LLMProvider $llm;
    private ?HttpClient $httpClient = null;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private OutputMode $mode;
    private array $options;
    private CanCompileMessages $messageCompiler;
    private ?InferenceRetryPolicy $retryPolicy;
    private bool $parallelToolCalls = false;
    private ToolExecutionFormatter $formatter;
    private CanHandleEvents $events;
    private CanCreateInference $inference;

    public function __construct(
        CanCreateInference $inference,
        ?LLMProvider $llm = null,
        ?HttpClient  $httpClient = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
        ?CanCompileMessages $messageCompiler = null,
        ?InferenceRetryPolicy $retryPolicy = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->inference = $inference;
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
        $this->messageCompiler = $messageCompiler ?? new ConversationWithCurrentToolTrace();
        $this->retryPolicy = $retryPolicy;
        $this->formatter = new ToolExecutionFormatter();
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function withLLMConfig(LLMConfig $config): static {
        $llm = $this->llm->withLLMConfig($config);
        return $this->with(
            llm: $llm,
            inference: InferenceRuntime::fromProvider(
                provider: $llm,
                events: $this->events,
                httpClient: $this->httpClient,
            ),
        );
    }

    #[\Override]
    public function messageCompiler(): CanCompileMessages {
        return $this->messageCompiler;
    }

    #[\Override]
    public function withMessageCompiler(CanCompileMessages $compiler): static {
        return $this->with(messageCompiler: $compiler);
    }

    #[Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor) : AgentState {
        $state = $this->ensureStateLLMConfig($state);
        $context = $this->messageCompiler->compile($state);
        $response = $this->getToolCallResponse($state, $tools, $context);
        $toolCalls = $response->toolCalls();
        $executions = $executor->executeTools($toolCalls, $state);
        $messages = $this->formatter->makeExecutionMessages($executions);
        $step = $this->buildStepFromResponse(
            response: $response,
            executions: $executions,
            followUps: $messages,
            context: $context,
        );
        return $state->withCurrentStep($step);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function with(
        ?LLMProvider $llm = null,
        ?HttpClient $httpClient = null,
        string|array|null $toolChoice = null,
        ?array $responseFormat = null,
        ?string $model = null,
        ?array $options = null,
        ?OutputMode $mode = null,
        ?CanCompileMessages $messageCompiler = null,
        ?InferenceRetryPolicy $retryPolicy = null,
        ?CanCreateInference $inference = null,
    ): static {
        return new static(
            inference: $inference ?? $this->inference,
            llm: $llm ?? $this->llm,
            httpClient: $httpClient ?? $this->httpClient,
            toolChoice: $toolChoice ?? $this->toolChoice,
            responseFormat: $responseFormat ?? $this->responseFormat,
            model: $model ?? $this->model,
            options: $options ?? $this->options,
            mode: $mode ?? $this->mode,
            messageCompiler: $messageCompiler ?? $this->messageCompiler,
            retryPolicy: $retryPolicy ?? $this->retryPolicy,
            events: $this->events,
        );
    }

    private function getToolCallResponse(AgentState $state, Tools $tools, Messages $messages) : InferenceResponse {
        $cache = $state->context()->toCachedContext($tools->toToolSchema());
        $cache = $cache->isEmpty() ? null : $cache;
        $requestStartedAt = new DateTimeImmutable();
        $this->emitInferenceRequestStarted($state, $messages->count(), $this->resolveModel($state));
        $response = $this->buildPendingInference($state, $messages, $tools, $cache)->response();
        $this->emitInferenceResponseReceived($state, $response, $requestStartedAt);
        return $response;
    }

    private function buildPendingInference(
        AgentState $state,
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

        $options = $this->options;
        if ($hasTools) {
            $options = array_merge($options, ['parallel_tool_calls' => $this->parallelToolCalls]);
        }

        $request = new InferenceRequest(
            messages: $messages,
            model: $this->model,
            tools: $toolSchemas,
            toolChoice: $hasTools ? $toolChoice : '',
            responseFormat: $this->responseFormat,
            options: $options,
            mode: $this->mode,
            cachedContext: $cache,
            retryPolicy: $this->retryPolicy,
        );

        return $this->inference->create($request);
    }

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

    private function ensureStateLLMConfig(AgentState $state): AgentState {
        if ($state->llmConfig() !== null) {
            return $state;
        }

        return $state->withLLMConfig($this->llm->resolveConfig());
    }

    private function resolveModel(AgentState $state): ?string {
        if ($this->model !== '') {
            return $this->model;
        }

        $model = $state->llmConfig()?->model ?? '';
        return $model !== '' ? $model : null;
    }

    // EVENT EMISSION ////////////////////////////////////////////

    private function emitInferenceRequestStarted(AgentState $state, int $messageCount, ?string $model): void {
        $this->events->dispatch(new InferenceRequestStarted(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            stepNumber: $state->stepCount() + 1,
            messageCount: $messageCount,
            model: $model,
        ));
    }

    private function emitInferenceResponseReceived(AgentState $state, ?InferenceResponse $response, DateTimeImmutable $requestStartedAt): void {
        $this->events->dispatch(new InferenceResponseReceived(
            agentId: $state->agentId()->toString(),
            parentAgentId: $state->parentAgentId()?->toString(),
            stepNumber: $state->stepCount() + 1,
            usage: $response?->usage(),
            finishReason: $response?->finishReason()?->value,
            requestStartedAt: $requestStartedAt,
        ));
    }
}
