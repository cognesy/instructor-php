<?php declare(strict_types=1);

namespace Cognesy\Agents\Data;

use Cognesy\Agents\Collections\AgentSteps;
use Cognesy\Agents\Collections\ErrorList;
use Cognesy\Agents\Collections\StepExecutions;
use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Context\AgentContext;
use Cognesy\Agents\Continuation\ExecutionContinuation;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Metadata;
use DateTimeImmutable;
use Throwable;

/**
 * Agent execution state.
 *
 * Session data (always present, persists across executions):
 * - Identity: agentId, parentAgentId
 * - Session timing: createdAt, updatedAt
 * - Context: messages, metadata, system prompt, response format
 * - Budget: resource limits inherited from parent or definition
 *
 * Execution data (optional, null when between executions):
 * - Execution identity: executionId
 * - Execution status and timing
 * - Step results and current step
 *
 * When execution is null, the agent is "between executions" and ready
 * for a fresh start. When present, it contains the current execution's
 * transient state (useful for mid-execution persistence/resume).
 */
final readonly class AgentState
{
    // Session data
    private AgentId $agentId;
    private ?AgentId $parentAgentId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private ?LLMConfig $llmConfig;
    private AgentBudget $budget;
    private int $executionCount; // allows agent to recognize e.g. first execution

    // Context data - messages, system prompt, etc
    private AgentContext $context;

    // Execution data - transient across executions
    private ?ExecutionState $execution;

    public function __construct(
        ?AgentId           $agentId = null,
        ?AgentId           $parentAgentId = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?AgentContext      $context = null,
        ?AgentBudget       $budget = null,
        ?LLMConfig         $llmConfig = null,
        int                $executionCount = 0,
        ?ExecutionState    $execution = null,
    ) {
        $now = new DateTimeImmutable();

        // Session data
        $this->agentId = $agentId ?? AgentId::generate();
        $this->parentAgentId = $parentAgentId;
        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $this->createdAt;
        $this->context = $context ?? new AgentContext();
        $this->budget = $budget ?? AgentBudget::unlimited();
        $this->llmConfig = $llmConfig;
        $this->executionCount = $executionCount;

        // Execution data (null = between executions)
        $this->execution = $execution;
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function empty(): self {
        return new self();
    }

    // STATE TRANSITIONS ////////////////////////////////////////

    public function withCurrentStepCompleted(?ExecutionStatus $status = null) : self {
        if ($this->execution?->currentStep() === null) {
            return $this;
        }

        $newExecution = match (true) {
            ($status === ExecutionStatus::Failed) => $this->ensureExecution()->withCurrentStepFailed(),
            $this->isFailed() => $this->ensureExecution()->withCurrentStepFailed(),
            default => $this->ensureExecution()->withCurrentStepCompleted(),
        };

        return $this->with(execution: $newExecution);
    }

    public function withExecutionCompleted(): self {
        return match(true) {
            $this->execution === null => $this->with(execution: ExecutionState::fresh()->completed()),
            $this->execution->isFailed() => $this->with(execution: $this->execution->completed(ExecutionStatus::Failed)),
            $this->execution->hasErrors() => $this->with(execution: $this->execution->completed(ExecutionStatus::Failed)),
            $this->wasForceStopped() => $this->with(execution: $this->execution->completed(ExecutionStatus::Stopped)),
            default => $this->with(execution: $this->execution->completed()),
        };
    }

    public function withExecutionContinued() : self {
        return $this->with(execution: $this->ensureExecution()->withContinuationRequested());
    }

    public function forNextExecution(): self {
        return new self(
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            context: $this->context,
            budget: $this->budget,
            llmConfig: $this->llmConfig,
            executionCount: $this->executionCount,
            execution: null,
        );
    }

    // MUTATORS ////////////////////////////////////////////////

    public function with(
        ?AgentId           $agentId = null,
        ?AgentId           $parentAgentId = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?AgentContext      $context = null,
        ?AgentBudget       $budget = null,
        ?LLMConfig         $llmConfig = null,
        ?int               $executionCount = null,
        ?ExecutionState    $execution = null,
    ): self {
        return new self(
            agentId: $agentId ?? $this->agentId,
            parentAgentId: $parentAgentId ?? $this->parentAgentId,
            createdAt: $createdAt ?? $this->createdAt,
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            context: $context ?? $this->context,
            budget: $budget ?? $this->budget,
            llmConfig: $llmConfig ?? $this->llmConfig,
            executionCount: $executionCount ?? $this->executionCount,
            execution: $execution ?? $this->execution,
        );
    }

    public function withFailure(Throwable $error): self {
        return $this->with(execution: $this->ensureExecution()->failed($error));
    }

    public function withStopSignal(StopSignal $signal): self {
        return $this->with(execution: $this->ensureExecution()->withStopSignal($signal));
    }

    public function withCurrentStep(AgentStep $step): self {
        $execution = $this->ensureExecution();
        $context = match(true) {
            $step->outputMessages()->isEmpty() => $this->context,
            default => $this->context->withAppendedMessages(
                $this->tagMessages($step->outputMessages(), $step, $execution)
            ),
        };
        return $this->with(
            context: $context,
            execution: $execution->withCurrentStep($step),
        );
    }

    public function withMessageStore(MessageStore $store): self {
        return $this->with(context: $this->context->withMessageStore($store));
    }

    public function withMessages(Messages $messages): self {
        return $this->with(context: $this->context->withMessages($messages));
    }

    public function withMetadata(string $name, mixed $value): self {
        return $this->with(context: $this->context->withMetadataKey($name, $value));
    }

    public function withUserMessage(string|Message $message): self {
        $userMessage = Messages::fromAny(Message::asUser($message));
        return $this->with(context: $this->context->withAppendedMessages($userMessage));
    }

    public function withSystemPrompt(string $systemPrompt): self {
        return $this->with(context: $this->context->withSystemPrompt($systemPrompt));
    }

    public function withExecutionStatus(ExecutionStatus $status): self {
        return $this->with(execution: $this->ensureExecution()->withStatus($status));
    }

    // ACCESSORS ////////////////////////////////////

    public function agentId(): AgentId {
        return $this->agentId;
    }

    public function parentAgentId(): ?AgentId {
        return $this->parentAgentId;
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    public function context(): AgentContext {
        return $this->context;
    }

    public function budget(): AgentBudget {
        return $this->budget;
    }

    public function llmConfig(): ?LLMConfig {
        return $this->llmConfig;
    }

    public function executionCount(): int {
        return $this->executionCount;
    }

    public function withBudget(AgentBudget $budget): self {
        return $this->with(budget: $budget);
    }

    public function withLLMConfig(?LLMConfig $llmConfig): self {
        return new self(
            agentId: $this->agentId,
            parentAgentId: $this->parentAgentId,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            context: $this->context,
            budget: $this->budget,
            llmConfig: $llmConfig,
            executionCount: $this->executionCount,
            execution: $this->execution,
        );
    }

    public function messages(): Messages {
        return $this->context->messages();
    }

    public function store(): MessageStore {
        return $this->context->store();
    }

    public function metadata(): Metadata {
        return $this->context->metadata();
    }

    public function execution(): ?ExecutionState {
        return $this->execution;
    }

    public function status(): ?ExecutionStatus {
        return $this->execution?->status();
    }

    private function isFailed() : bool {
        return match (true) {
            $this->execution === null => false,
            $this->execution->isFailed() => true,
            default => false,
        };
    }

    private function wasForceStopped(): bool {
        $primarySignal = $this->execution?->continuation()->stopSignals()->first();
        return $primarySignal !== null && $primarySignal->reason->wasForceStopped();
    }

    public function currentStep(): ?AgentStep {
        return $this->execution?->currentStep();
    }

    public function hasCurrentStep(): bool {
        return $this->execution?->currentStep() !== null;
    }

    public function currentStepOrLast(): ?AgentStep {
        $current = $this->currentStep();
        if ($current !== null) {
            return $current;
        }
        return $this->lastStepExecution()?->step();
    }

    public function hasFinalResponse(): bool {
        $step = $this->finalResponseStep();
        return match (true) {
            $step === null => false,
            trim($step->outputMessages()->toString()) === '' => false,
            default => true,
        };
    }

    public function finalResponse(): Messages {
        $step = $this->finalResponseStep();
        return match (true) {
            $step === null => Messages::empty(),
            trim($step->outputMessages()->toString()) === '' => Messages::empty(),
            default => $step->outputMessages(),
        };
    }

    public function currentResponse(): Messages {
        $final = $this->finalResponse();
        if ($final->notEmpty()) {
            return $final;
        }
        $step = $this->currentStepOrLast();
        if ($step === null) {
            return Messages::empty();
        }
        $output = $step->outputMessages();
        return trim($output->toString()) === '' ? Messages::empty() : $output;
    }

    public function stepCount(): int {
        // Source of truth is ExecutionState::stepCount(); do not derive from
        // rewindable context/messages.
        return $this->execution?->stepCount() ?? 0;
    }

    public function steps(): AgentSteps {
        return $this->stepExecutions()->steps();
    }

    public function stepExecutions(): StepExecutions {
        return $this->execution?->stepExecutions() ?? StepExecutions::empty();
    }

    public function lastStepExecution(): ?StepExecution {
        return $this->stepExecutions()->last();
    }

    public function lastStep(): ?AgentStep {
        return $this->lastStepExecution()?->step();
    }

    public function lastStepToolExecutions(): ToolExecutions {
        return $this->lastStepExecution()?->step()->toolExecutions() ?? ToolExecutions::none();
    }

    public function lastToolExecution(): ?ToolExecution {
        $executions = $this->lastStepToolExecutions()->all();
        return $executions !== [] ? $executions[array_key_last($executions)] : null;
    }

    public function lastStepErrors(): ErrorList {
        return $this->lastStepExecution()?->step()->errors() ?? ErrorList::empty();
    }

    public function lastStepType(): ?AgentStepType {
        return $this->lastStepExecution()?->step()->stepType();
    }

    public function lastStepUsage(): Usage {
        return $this->lastStepExecution()?->step()->usage() ?? Usage::none();
    }

    public function lastStepDuration(): ?float {
        return $this->lastStepExecution()?->duration();
    }

    public function lastStopSignal(): ?StopSignal {
        $fromStep = $this->lastStepExecution()?->continuation()->stopSignals()->first();
        if ($fromStep !== null) {
            return $fromStep;
        }
        return $this->executionContinuation()?->stopSignals()->first();
    }

    public function lastStopReason(): ?StopReason {
        return $this->lastStopSignal()?->reason;
    }

    public function lastStopSource(): ?string {
        return $this->lastStopSignal()?->source;
    }

    public function lastStepStartedAt(): ?DateTimeImmutable {
        return $this->lastStepExecution()?->startedAt();
    }

    public function lastStepCompletedAt(): ?DateTimeImmutable {
        return $this->lastStepExecution()?->completedAt();
    }

    public function hasCurrentExecution(): bool {
        return $this->execution?->currentStep() !== null;
    }

    public function shouldStop() : bool {
        return $this->execution?->shouldStop() ?? true;
    }

    public function executionContinuation(): ?ExecutionContinuation {
        return $this->ensureExecution()->continuation();
    }

    public function executionDuration(): ?float {
        return $this->execution?->totalDuration();
    }

    public function currentStepDuration(): ?float {
        return $this->execution?->currentStepDuration();
    }

    public function usage(): Usage {
        return $this->execution?->usage() ?? Usage::none();
    }

    public function hasErrors() : ?bool {
        return $this->execution?->hasErrors();
    }

    public function errors() : ErrorList {
        return $this->execution?->errors() ?? ErrorList::empty();
    }

    // DEBUG /////////////////////////////////////////////////////

    /**
     * Get a summary of the agent state for debugging.
     * This is the primary way to understand what happened during execution.
     */
    public function debug(): array
    {
        return [
            'status' => $this->status(),
            'executionCount' => $this->executionCount,
            'hasExecution' => $this->execution !== null,
            'executionId' => $this->execution?->executionId()->toString(),
            'steps' => $this->stepCount(),
            'continuation' => $this->executionContinuation()?->explain() ?? '-',
            'hasErrors' => $this->hasErrors() ?? false,
            'errors' => $this->errors(),
            'usage' => $this->usage()->toArray(),
        ];
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray(): array {
        return [
            'agentId' => $this->agentId->value,
            'parentAgentId' => $this->parentAgentId?->value,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'context' => $this->context->toArray(),
            'budget' => $this->budget->toArray(),
            'llmConfig' => $this->llmConfig?->toArray(),
            'executionCount' => $this->executionCount,
            'execution' => $this->execution?->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        $execution = match (true) {
            is_array($data['execution'] ?? null) => ExecutionState::fromArray($data['execution']),
            default => null,
        };

        $budget = match (true) {
            is_array($data['budget'] ?? null) => AgentBudget::fromArray($data['budget']),
            default => null,
        };

        $llmConfig = match (true) {
            is_array($data['llmConfig'] ?? null) => LLMConfig::fromArray($data['llmConfig']),
            default => null,
        };

        return new self(
            agentId: isset($data['agentId']) ? new AgentId($data['agentId']) : null,
            parentAgentId: isset($data['parentAgentId']) ? new AgentId($data['parentAgentId']) : null,
            createdAt: self::parseDateFrom($data, 'createdAt'),
            updatedAt: self::parseDateFrom($data, 'updatedAt'),
            context: AgentContext::fromArray($data['context'] ?? []),
            budget: $budget,
            llmConfig: $llmConfig,
            executionCount: $data['executionCount'] ?? 0,
            execution: $execution,
        );
    }

    // PARSING HELPERS //////////////////////////////////////////

    private static function parseDateFrom(array $data, string $key): DateTimeImmutable {
        $value = $data[$key] ?? null;
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }

    private function finalResponseStep(): ?AgentStep {
        $step = $this->currentStepOrLast();
        return match (true) {
            $step === null => null,
            $step->stepType() !== AgentStepType::FinalResponse => null,
            default => $step,
        };
    }

    private function ensureExecution(): ExecutionState {
        return $this->execution ?? ExecutionState::fresh();
    }

    private function tagMessages(Messages $messages, AgentStep $step, ExecutionState $execution): Messages {
        $executionId = $execution->executionId()->toString();
        $isTrace = $step->stepType() !== AgentStepType::FinalResponse;
        $tagged = Messages::empty();
        foreach ($messages->each() as $msg) {
            $msg = $msg->withMetadata('step_id', $step->id())
                ->withMetadata('execution_id', $executionId)
                ->withMetadata('agent_id', $this->agentId->value);
            if ($isTrace) {
                $msg = $msg->withMetadata('is_trace', true);
            }
            $tagged = $tagged->appendMessage($msg);
        }
        return $tagged;
    }

}
