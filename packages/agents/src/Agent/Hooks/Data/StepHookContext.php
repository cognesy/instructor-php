<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Data;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;

/**
 * Context for step-related hook events (BeforeStep, AfterStep).
 *
 * Provides access to:
 * - The current step index
 * - The completed step (AfterStep only)
 * - The current agent state
 *
 * For BeforeStep events, the step is null (not yet created).
 * For AfterStep events, the step contains the completed step data.
 *
 * @example
 * function beforeStep(StepHookContext $ctx): HookOutcome {
 *     $stepIndex = $ctx->stepIndex();
 *
 *     // Log step start
 *     $this->logger->info("Starting step {$stepIndex}");
 *
 *     // Add timing metadata
 *     return HookOutcome::proceed(
 *         $ctx->withMetadata('step_started', microtime(true))
 *     );
 * }
 *
 * @example
 * function afterStep(StepHookContext $ctx): HookOutcome {
 *     $step = $ctx->step();
 *     if ($step?->hasErrors()) {
 *         $this->logger->warning("Step had errors: {$step->errorsAsString()}");
 *     }
 *     return HookOutcome::proceed();
 * }
 */
final readonly class StepHookContext extends AbstractHookContext
{
    /**
     * @param AgentState $state The current agent state
     * @param int $stepIndex The current step index (0-based)
     * @param HookType $event The specific event (BeforeStep or AfterStep)
     * @param AgentStep|null $step The completed step (AfterStep only)
     * @param array<string, mixed> $metadata Additional context metadata
     */
    public function __construct(
        AgentState         $state,
        private int        $stepIndex,
        private HookType   $event = HookType::BeforeStep,
        private ?AgentStep $step = null,
        array              $metadata = [],
    ) {
        parent::__construct($state, $metadata);
    }

    /**
     * Create a context for BeforeStep event.
     *
     * @param AgentState $state The current agent state
     * @param int $stepIndex The step index about to execute
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function beforeStep(
        AgentState $state,
        int $stepIndex,
        array $metadata = [],
    ): self {
        return new self($state, $stepIndex, HookType::BeforeStep, null, $metadata);
    }

    /**
     * Create a context for AfterStep event.
     *
     * @param AgentState $state The current agent state
     * @param int $stepIndex The step index that completed
     * @param AgentStep $step The completed step
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function afterStep(
        AgentState $state,
        int $stepIndex,
        AgentStep $step,
        array $metadata = [],
    ): self {
        return new self($state, $stepIndex, HookType::AfterStep, $step, $metadata);
    }

    #[\Override]
    public function eventType(): HookType
    {
        return $this->event;
    }

    /**
     * Get the current step index (0-based).
     */
    public function stepIndex(): int
    {
        return $this->stepIndex;
    }

    /**
     * Get the step number (1-based, for display purposes).
     */
    public function stepNumber(): int
    {
        return $this->stepIndex + 1;
    }

    /**
     * Get the completed step.
     *
     * Only available for AfterStep events.
     */
    public function step(): ?AgentStep
    {
        return $this->step;
    }

    /**
     * Check if this is a BeforeStep event.
     */
    public function isBeforeStep(): bool
    {
        return $this->event === HookType::BeforeStep;
    }

    /**
     * Check if this is an AfterStep event.
     */
    public function isAfterStep(): bool
    {
        return $this->event === HookType::AfterStep;
    }

    #[\Override]
    public function withState(AgentState $state): static
    {
        return new self($state, $this->stepIndex, $this->event, $this->step, $this->metadata);
    }

    #[\Override]
    public function withMetadata(string $key, mixed $value): static
    {
        return new self(
            $this->state,
            $this->stepIndex,
            $this->event,
            $this->step,
            [...$this->metadata, $key => $value],
        );
    }
}
