<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct;

use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Drivers\ReAct\Actions\MakeReActPrompt;
use Cognesy\Agents\Drivers\ReAct\Actions\MakeToolCalls;
use Cognesy\Agents\Drivers\ReAct\Data\DecisionWithDetails;
use Cognesy\Agents\Drivers\ReAct\Data\ReActDecision;
use Cognesy\Agents\Drivers\ReAct\Utils\ReActFormatter;
use Cognesy\Agents\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Agents\Events\AgentEventEmitter;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Agents\Hooks\CanInterceptAgentLifecycle;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\PassThroughInterceptor;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Data\CachedContext as StructuredCachedContext;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

final class ReActDriver implements CanUseTools
{
    private LLMProvider $llm;
    private ?HttpClient $httpClient = null;
    private string $model;
    private array $options;
    private bool $finalViaInference;
    private ?string $finalModel;
    private array $finalOptions;
    private int $maxRetries;
    private OutputMode $mode;
    private CanEmitAgentEvents $eventEmitter;

    public function __construct(
        ?LLMProvider $llm = null,
        ?HttpClient $httpClient = null,
        string $model = '',
        array $options = [],
        bool $finalViaInference = false,
        ?string $finalModel = null,
        array $finalOptions = [],
        int $maxRetries = 2,
        OutputMode $mode = OutputMode::Json,
        ?CanEmitAgentEvents $eventEmitter = null,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->model = $model;
        $this->options = $options;
        $this->finalViaInference = $finalViaInference;
        $this->finalModel = $finalModel;
        $this->finalOptions = $finalOptions;
        $this->maxRetries = $maxRetries;
        $this->mode = $mode;
        $this->eventEmitter = $eventEmitter ?? new AgentEventEmitter();
    }

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
        $messages = $state->context()->messagesForInference();
        $system = $this->buildSystemPrompt($tools);
        $cachedContext = $this->structuredCachedContext($state);

        // Emit inference request started event
        $requestStartedAt = new DateTimeImmutable();
        $this->eventEmitter->inferenceRequestStarted($state, $messages->count(), $this->model ?: null);

        $extraction = Result::try(fn() => $this->extractDecision($messages, $system, $cachedContext));

        if ($extraction->isFailure()) {
            $error = $extraction->error();
            $this->eventEmitter->decisionExtractionFailed(
                state: $state,
                errorMessage: $error->getMessage(),
                errorType: get_class($error),
                attemptNumber: 1,
                maxAttempts: $this->maxRetries,
            );
            $step = $this->buildExtractionFailureStep($error, $messages);
            return $state->withCurrentStep($step)->withFailure($error);
        }

        $bundle = $extraction->unwrap();
        $decision = $bundle->decision();
        $inferenceResponse = $bundle->response();

        $inferenceResponse = $this->resolveInferenceResponse($state, $inferenceResponse);

        // Emit inference response received event
        $this->eventEmitter->inferenceResponseReceived($state, $inferenceResponse, $requestStartedAt);

        // Basic validation: decision type + tool exists
        $validator = new ReActValidator();
        $validation = $validator->validateBasicDecision($decision, $tools->names());
        if ($validation->isInvalid()) {
            $this->eventEmitter->validationFailed(
                state: $state,
                validationType: 'decision',
                errors: [$validation->getErrorMessage()],
            );
            $step = $this->buildValidationFailureStep($validation, $messages);
            return $state->withCurrentStep($step)->withFailure(new AgentException($validation->getErrorMessage()));
        }

        // Extract tool calls independent of execution
        $toolCalls = (new MakeToolCalls($tools, $validator))($decision);

        $usage = $inferenceResponse->usage();

        if (!$decision->isCall()) {
            $step = $this->buildFinalAnswerStep($decision, $usage, $inferenceResponse, $messages, $state->context()->cache());
            return $state->withCurrentStep($step);
        }

        // Execute tool calls and assemble follow-ups
        $executions = $executor->executeTools($toolCalls, $state);

        $outputMessages = $this->makeFollowUps($decision, $executions);
        $responseWithCalls = $this->withToolCalls($inferenceResponse, $toolCalls);

        $step = new AgentStep(
            inputMessages: $messages,
            outputMessages: $outputMessages,
            inferenceResponse: $responseWithCalls,
            toolExecutions: $executions,
        );

        return $state->withCurrentStep($step);
    }

    // MUTATORS ////////////////////////////////////////////////////////////////

    public function withLLMProvider(LLMProvider $llm): self {
        $clone = clone $this;
        $clone->llm = $llm;
        return $clone;
    }

    public function withEventEmitter(CanEmitAgentEvents $eventEmitter): self {
        $clone = clone $this;
        $clone->eventEmitter = $eventEmitter;
        return $clone;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function resolveInferenceResponse(AgentState $state, InferenceResponse $fallback): InferenceResponse {
        return $state->execution()?->currentStep()?->inferenceResponse() ?? $fallback;
    }

    private function buildSystemPrompt(Tools $tools): string {
        return (new MakeReActPrompt($tools))();
    }

    private function extractDecision(Messages $messages, string $system, ?StructuredCachedContext $cachedContext): DecisionWithDetails {
        $pending = $this->extractDecisionWithUsage(
            messages: $messages,
            system: $system,
            decisionModel: ReActDecision::class,
            cachedContext: $cachedContext,
        );
        /** @var ReActDecision $decision */
        $decision = $pending->get();
        return new DecisionWithDetails(
            decision: $decision,
            response: $pending->response(),
        );
    }

    /**
     * Extracts a ReAct decision via StructuredOutput and returns usage data.
     *
     * @param class-string|array|object $decisionModel
     */
    private function extractDecisionWithUsage(
        Messages $messages,
        string $system,
        string|array|object $decisionModel,
        ?StructuredCachedContext $cachedContext,
    ): PendingStructuredOutput {
        $structured = (new StructuredOutput())
            ->withSystem($system)
            ->withMessages($messages)
            ->withResponseModel($decisionModel)
            ->withOutputMode($this->mode)
            ->withModel($this->model)
            ->withOptions($this->options)
            ->withMaxRetries($this->maxRetries)
            ->withLLMProvider($this->llm);
        if ($cachedContext !== null && !$cachedContext->isEmpty()) {
            $structured = $structured->withCachedContext(
                messages: $cachedContext->messages()->toArray(),
                system: $cachedContext->system(),
                prompt: $cachedContext->prompt(),
                examples: $cachedContext->examples(),
            );
        }
        if ($this->httpClient !== null) {
            $structured = $structured->withHttpClient($this->httpClient);
        }
        return $structured->create();
    }

    /** Builds a failure step when decision fails validation. */
    private function buildValidationFailureStep(ValidationResult $validation, Messages $context): AgentStep {
        $formatter = new ReActFormatter();
        $error = new \RuntimeException($validation->getErrorMessage());
        $messagesErr = $formatter->decisionExtractionErrorMessages($error);
        $exec = new ToolExecution(
            new ToolCall('decision_validation', []),
            Result::failure($error),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new AgentStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            toolExecutions: $executions,
        );
    }

    /** Builds a failure step when decision extraction fails. */
    private function buildExtractionFailureStep(\Throwable $e, Messages $context): AgentStep {
        $formatter = new ReActFormatter();
        $messagesErr = $formatter->decisionExtractionErrorMessages($e);
        $exec = new ToolExecution(
            new ToolCall('decision_extraction', []),
            Result::failure($e),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new AgentStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            toolExecutions: $executions,
        );
    }

    /** Builds a step for a final_answer decision (optionally finalizes via Inference). */
    private function buildFinalAnswerStep(
        ReActDecision          $decision,
        ?Usage                 $usage,
        ?InferenceResponse     $inferenceResponse,
        Messages               $messages,
        CachedInferenceContext $cachedContext,
    ): AgentStep {
        $finalText = $decision->answer();
        if ($this->finalViaInference) {
            $pending = $this->finalizeAnswerViaInference($messages, $cachedContext);
            $inferenceResponse = $pending->response();
            $finalText = $inferenceResponse->content();
            $usage = $inferenceResponse->usage();
        }
        $responseWithUsage = $this->withUsage($inferenceResponse, $usage);
        return new AgentStep(
            inputMessages: $messages,
            outputMessages: Messages::empty()->appendMessage(Message::asAssistant($finalText)),
            inferenceResponse: $responseWithUsage,
        );
    }

    /** Creates follow-up messages including assistant Thought/Action and observations. */
    private function makeFollowUps(ReActDecision $decision, ToolExecutions $executions): Messages {
        $formatter = new ReActFormatter();
        $messages = Messages::empty()->appendMessage($formatter->assistantThoughtActionMessage($decision));
        foreach ($executions->all() as $execution) {
            $messages = $messages->appendMessage($formatter->observationMessage($execution));
        }
        return $messages;
    }

    /** Generates a plain-text final answer via Inference and returns PendingInference. */
    private function finalizeAnswerViaInference(Messages $messages, CachedInferenceContext $cachedContext): PendingInference {
        $finalMessages = Messages::fromArray([
            ['role' => 'system', 'content' => 'Return only the final answer as plain text.'],
            ...$messages->toArray(),
        ]);
        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            ->withMessages($finalMessages->toArray())
            ->withModel($this->finalModel ?: $this->model)
            ->withOptions($this->finalOptions ?: $this->options)
            ->withOutputMode(OutputMode::Text);
        if (!$cachedContext->isEmpty()) {
            $inference = $inference->withCachedContext(
                messages: $cachedContext->messages()->toArray(),
            );
        }
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference->create();
    }

    private function withToolCalls(InferenceResponse $response, ToolCalls $toolCalls): InferenceResponse {
        if ($toolCalls->hasNone()) {
            return $response;
        }
        return $response->with(toolCalls: $toolCalls);
    }

    private function withUsage(?InferenceResponse $response, ?Usage $usage): InferenceResponse {
        $resolved = $response ?? new InferenceResponse();
        if ($usage === null) {
            return $resolved;
        }
        return $resolved->with(usage: $usage);
    }

    private function structuredCachedContext(AgentState $state): ?StructuredCachedContext {
        $cache = $state->context()->cache();
        if ($cache->isEmpty()) {
            return null;
        }
        return new StructuredCachedContext(
            messages: $cache->messages()->toArray(),
        );
    }
}
