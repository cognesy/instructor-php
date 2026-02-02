<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Exceptions\ToolExecutionBlockedException;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use DateTimeImmutable;
use Throwable;

class HookContext
{
    private readonly HookTrigger $triggerType;
    private readonly DateTimeImmutable $createdAt;

    private readonly DateTimeImmutable $updatedAt;

    private readonly AgentState $state;
    private readonly ?ToolCall $toolCall;
    private bool $isToolExecutionBlocked;
    private readonly ?ToolExecution $toolExecution;
    private readonly ErrorList $errorList;
    private readonly array $metadata;

    public function __construct(
        HookTrigger        $triggerType,
        AgentState         $state,
        array              $metadata = [],
        ?ToolCall          $toolCall = null,
        ?bool              $isToolExecutionBlocked = null,
        ?ToolExecution     $toolExecution = null,
        ?ErrorList         $errorList = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $now = new DateTimeImmutable();

        $this->triggerType = $triggerType;
        $this->state = $state;
        $this->metadata = $metadata;
        $this->toolCall = $toolCall;
        $this->isToolExecutionBlocked = $isToolExecutionBlocked ?? false;
        $this->toolExecution = $toolExecution;
        $this->errorList = $errorList ?? ErrorList::empty();

        $this->createdAt = $createdAt ?? $now;
        $this->updatedAt = $updatedAt ?? $now;
    }

    public static function beforeExecution(
        AgentState $state,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::BeforeExecution,
            state: $state,
            metadata: $metadata,
        );
    }

    public static function beforeStep(
        AgentState $state,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::BeforeStep,
            state: $state,
            metadata: $metadata,
        );
    }

    public static function beforeToolUse(
        AgentState $state,
        ToolCall $toolCall,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::BeforeToolUse,
            state: $state,
            metadata: $metadata,
            toolCall: $toolCall,
        );
    }

    public static function afterToolUse(
        AgentState $state,
        ToolExecution $toolExecution,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::AfterToolUse,
            state: $state,
            metadata: $metadata,
            toolExecution: $toolExecution,
        );
    }

    public static function afterStep(
        AgentState $state,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::AfterStep,
            state: $state,
            metadata: $metadata,
        );
    }

    public static function afterExecution(
        AgentState $state,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::AfterExecution,
            state: $state,
            metadata: $metadata,
        );
    }

    public static function onError(
        AgentState $state,
        ErrorList $errorList,
        array $metadata = [],
    ): self {
        return new self(
            triggerType: HookTrigger::OnError,
            state: $state,
            metadata: $metadata,
            errorList: $errorList,
        );
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?AgentState     $state = null,
        ?array          $metadata = null,
        ?ToolCall       $toolCall = null,
        ?bool           $isToolExecutionBlocked = null,
        ?ToolExecution  $toolExecution = null,
        ?ErrorList      $errorList = null,
    ): self {
        return new self(
            triggerType: $this->triggerType,
            state: $state ?? $this->state,
            metadata: $metadata ?? $this->metadata,
            toolCall: $toolCall ?? $this->toolCall,
            isToolExecutionBlocked: $isToolExecutionBlocked ?? $this->isToolExecutionBlocked,
            toolExecution: $toolExecution ?? $this->toolExecution,
            errorList: $errorList ?? $this->errorList,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withState(AgentState $state): self {
        return $this->with(state: $state);
    }

    public function withMetadata(array $metadata): self {
        return $this->with(metadata: $metadata);
    }

    public function withToolCall(ToolCall $toolCall): self {
        return $this->with(toolCall: $toolCall);
    }

    public function withToolExecution(ToolExecution $toolExecution): self {
        return $this->with(toolExecution: $toolExecution);
    }

    public function withErrorList(ErrorList $errorList): self {
        return $this->with(errorList: $errorList);
    }

    public function withToolExecutionBlocked(?string $message = null) : self {
        $message = $message ?? 'Execution blocked by hook: ' . $this->hookToString();
        $toolCall = $this->toolCall ?? ToolCall::none();
        $exception = new ToolExecutionBlockedException($message, $toolCall);
        return $this->with(
            isToolExecutionBlocked: true,
            toolExecution: ToolExecution::blocked(
                toolCall: $toolCall,
                message: $message,
            ),
            errorList: ErrorList::with($exception),
        );
    }

    public function withError(Throwable $error): self {
        return $this->withErrorList($this->errorList->withAppendedExceptions($error));
    }

    // ACCESSORS ////////////////////////////////////////////

    public function triggerType(): HookTrigger {
        return $this->triggerType;
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    public function state(): AgentState {
        return $this->state;
    }

    public function toolCall(): ?ToolCall {
        return $this->toolCall;
    }

    public function toolExecution(): ?ToolExecution {
        return $this->toolExecution;
    }

    public function errorList(): ErrorList {
        return $this->errorList;
    }

    public function metadata(?string $key = null, mixed $default = null): mixed {
        return match(true) {
            ($key === null) => $this->metadata,
            array_key_exists($key, $this->metadata) => $this->metadata[$key],
            default => $default,
        };
    }

    public function hasErrors(): bool {
        return !$this->errorList->isEmpty();
    }

    public function isToolExecutionBlocked(): bool {
        return match(true) {
            $this->isToolExecutionBlocked => true,
            !is_null($this->toolExecution) && $this->toolExecution->wasBlocked() => true,
            !$this->hasErrors() => false,
            $this->errorList->hasError(ToolExecutionBlockedException::class) => true,
            default => false,
        };
    }

    private function hookToString(): string {
        return sprintf(
            "HookContext(triggerType=%s, toolCall=%s, toolExecution=%s, errors=%d)",
            $this->triggerType->name,
            $this->toolCall ? $this->toolCall->name() . "(" . $this->toolCall->argsAsJson() . ")" : 'null',
            $this->toolExecution ? $this->toolExecution->name() : 'null',
            $this->errorList->count()
        );
    }
}