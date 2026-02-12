<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling;

use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanAcceptEventHandler;
use Cognesy\Agents\Core\Contracts\CanAcceptLLMProvider;
use Cognesy\Agents\Core\Contracts\CanAcceptMessageCompiler;
use Cognesy\Agents\Core\Contracts\CanCompileMessages;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
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
class ToolCallingDriver implements CanUseTools, CanAcceptEventHandler, CanAcceptLLMProvider, CanAcceptMessageCompiler
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

    public function __construct(
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
    public function withLLMProvider(LLMProvider $llm): static {
        return new self(
            llm: $llm,
            httpClient: $this->httpClient,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            model: $this->model,
            options: $this->options,
            mode: $this->mode,
            messageCompiler: $this->messageCompiler,
            retryPolicy: $this->retryPolicy,
            events: $this->events,
        );
    }

    #[\Override]
    public function llmProvider(): LLMProvider {
        return $this->llm;
    }

    #[\Override]
    public function withEventHandler(CanHandleEvents $events): static {
        return new self(
            llm: $this->llm,
            httpClient: $this->httpClient,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            model: $this->model,
            options: $this->options,
            mode: $this->mode,
            messageCompiler: $this->messageCompiler,
            retryPolicy: $this->retryPolicy,
            events: $events,
        );
    }

    #[\Override]
    public function messageCompiler(): CanCompileMessages {
        return $this->messageCompiler;
    }

    #[\Override]
    public function withMessageCompiler(CanCompileMessages $compiler): static {
        return new self(
            llm: $this->llm,
            httpClient: $this->httpClient,
            toolChoice: $this->toolChoice,
            responseFormat: $this->responseFormat,
            model: $this->model,
            options: $this->options,
            mode: $this->mode,
            messageCompiler: $compiler,
            retryPolicy: $this->retryPolicy,
            events: $this->events,
        );
    }

    #[Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor) : AgentState {
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

    private function getToolCallResponse(AgentState $state, Tools $tools, Messages $messages) : InferenceResponse {
        $cache = $state->context()->toCachedContext($tools->toToolSchema());
        $cache = $cache->isEmpty() ? null : $cache;
        $requestStartedAt = new DateTimeImmutable();
        $this->emitInferenceRequestStarted($state, $messages->count(), $this->model ?: null);
        $response = $this->buildPendingInference($messages, $tools, $cache)->response();
        $this->emitInferenceResponseReceived($state, $response, $requestStartedAt);
        return $response;
    }

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

    // EVENT EMISSION ////////////////////////////////////////////

    private function emitInferenceRequestStarted(AgentState $state, int $messageCount, ?string $model): void {
        $this->events->dispatch(new InferenceRequestStarted(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            messageCount: $messageCount,
            model: $model,
        ));
    }

    private function emitInferenceResponseReceived(AgentState $state, ?InferenceResponse $response, DateTimeImmutable $requestStartedAt): void {
        $this->events->dispatch(new InferenceResponseReceived(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            usage: $response?->usage(),
            finishReason: $response?->finishReason()?->value,
            requestStartedAt: $requestStartedAt,
        ));
    }
}
