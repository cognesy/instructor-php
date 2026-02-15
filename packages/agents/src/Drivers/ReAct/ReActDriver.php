<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct;

use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Context\Compilers\ConversationWithCurrentToolTrace;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\AgentStep;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\ReAct\Actions\MakeReActPrompt;
use Cognesy\Agents\Drivers\ReAct\Actions\MakeToolCalls;
use Cognesy\Agents\Drivers\ReAct\Data\DecisionWithDetails;
use Cognesy\Agents\Drivers\ReAct\Data\ReActDecision;
use Cognesy\Agents\Drivers\ReAct\Utils\ReActFormatter;
use Cognesy\Agents\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Agents\Events\DecisionExtractionFailed;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\InferenceResponseReceived;
use Cognesy\Agents\Events\ValidationFailed;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;
use Cognesy\Events\Contracts\CanAcceptEventHandler;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Data\CachedContext as StructuredCachedContext;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMProvider;
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

final class ReActDriver implements CanUseTools, CanAcceptEventHandler, CanAcceptLLMProvider, CanAcceptMessageCompiler
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
    private CanCompileMessages $messageCompiler;
    private CanHandleEvents $events;

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
        ?CanCompileMessages $messageCompiler = null,
        ?CanHandleEvents $events = null,
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
        $this->messageCompiler = $messageCompiler ?? new ConversationWithCurrentToolTrace();
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState {
        $messages = $this->messageCompiler->compile($state);
        $system = $this->buildSystemPrompt($tools);
        $cachedContext = $this->structuredCachedContext($state);

        $requestStartedAt = new DateTimeImmutable();
        $this->emitInferenceRequestStarted($state, $messages->count(), $this->model ?: null);

        $extraction = Result::try(fn() => $this->extractDecision($messages, $system, $cachedContext));

        if ($extraction->isFailure()) {
            $error = $extraction->error();
            $this->emitDecisionExtractionFailed(
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

        $this->emitInferenceResponseReceived($state, $inferenceResponse, $requestStartedAt);

        $validator = new ReActValidator();
        $validation = $validator->validateBasicDecision($decision, $tools->names());
        if ($validation->isInvalid()) {
            $this->emitValidationFailed(
                state: $state,
                validationType: 'decision',
                errors: [$validation->getErrorMessage()],
            );
            $step = $this->buildValidationFailureStep($validation, $messages);
            return $state->withCurrentStep($step)->withFailure(new AgentException($validation->getErrorMessage()));
        }

        $toolCalls = (new MakeToolCalls($tools, $validator))($decision);

        $usage = $inferenceResponse->usage();

        if (!$decision->isCall()) {
            $step = $this->buildFinalAnswerStep($decision, $usage, $inferenceResponse, $messages, $state->context()->toCachedContext());
            return $state->withCurrentStep($step);
        }

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

    // ACCESSORS ///////////////////////////////////////////////////////////////

    #[\Override]
    public function llmProvider(): LLMProvider {
        return $this->llm;
    }

    // MUTATORS ////////////////////////////////////////////////////////////////

    #[\Override]
    public function withLLMProvider(LLMProvider $llm): static {
        $clone = clone $this;
        $clone->llm = $llm;
        return $clone;
    }

    #[\Override]
    public function withEventHandler(CanHandleEvents $events): static {
        $clone = clone $this;
        $clone->events = $events;
        return $clone;
    }

    #[\Override]
    public function messageCompiler(): CanCompileMessages {
        return $this->messageCompiler;
    }

    #[\Override]
    public function withMessageCompiler(CanCompileMessages $compiler): static {
        $clone = clone $this;
        $clone->messageCompiler = $compiler;
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

    private function makeFollowUps(ReActDecision $decision, ToolExecutions $executions): Messages {
        $formatter = new ReActFormatter();
        $messages = Messages::empty()->appendMessage($formatter->assistantThoughtActionMessage($decision));
        foreach ($executions->all() as $execution) {
            $messages = $messages->appendMessage($formatter->observationMessage($execution));
        }
        return $messages;
    }

    private function finalizeAnswerViaInference(Messages $messages, CachedInferenceContext $cache): PendingInference {
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
        if (!$cache->isEmpty()) {
            $inference = $inference->withCachedContext(
                messages: $cache->messages()->toArray(),
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
        $systemPrompt = $state->context()->systemPrompt();
        if ($systemPrompt === '') {
            return null;
        }
        return new StructuredCachedContext(
            messages: [['role' => 'system', 'content' => $systemPrompt]],
        );
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

    private function emitDecisionExtractionFailed(AgentState $state, string $errorMessage, string $errorType, int $attemptNumber, int $maxAttempts): void {
        $this->events->dispatch(new DecisionExtractionFailed(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            errorMessage: $errorMessage,
            errorType: $errorType,
            attemptNumber: $attemptNumber,
            maxAttempts: $maxAttempts,
        ));
    }

    private function emitValidationFailed(AgentState $state, string $validationType, array $errors): void {
        $this->events->dispatch(new ValidationFailed(
            agentId: $state->agentId(),
            parentAgentId: $state->parentAgentId(),
            stepNumber: $state->stepCount() + 1,
            validationType: $validationType,
            errors: $errors,
        ));
    }
}
