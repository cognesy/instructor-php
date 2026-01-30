<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Contracts;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;

/**
 * Core interface for all hooks in the unified hook system.
 *
 * Hooks are simple state transformers that:
 * 1. Receive AgentState directly (context available via currentExecution)
 * 2. Write evaluations to state for flow control
 * 3. Return modified AgentState (or same state if no changes)
 * 4. Declare which event types they handle
 *
 * Flow control is unified through evaluations:
 * - Guards write ForbidContinuation/AllowContinuation
 * - Work drivers write RequestContinuation/AllowStop
 * - AgentLoop aggregates evaluations into ContinuationOutcome
 *
 * @example
 * class StepsLimitHook implements Hook {
 *     public function appliesTo(): array {
 *         return [HookType::BeforeStep];
 *     }
 *
 *     public function process(AgentState $state, HookType $event): AgentState {
 *         $currentSteps = $state->stepCount();
 *         if ($currentSteps >= $this->maxSteps) {
 *             return $state->withEvaluation(
 *                 ContinuationEvaluation::fromDecision(
 *                     self::class,
 *                     ContinuationDecision::ForbidContinuation,
 *                     StopReason::StepsLimitReached,
 *                 )
 *             );
 *         }
 *         return $state->withEvaluation(
 *             ContinuationEvaluation::fromDecision(
 *                 self::class,
 *                 ContinuationDecision::AllowContinuation,
 *             )
 *         );
 *     }
 * }
 */
interface Hook
{
    /**
     * Get the event types this hook handles.
     *
     * @return list<HookType>
     */
    public function appliesTo(): array;

    /**
     * Process the state and return (potentially modified) state.
     *
 * Hooks should:
 * - Read event context from $state->hookContext()
 * - Write evaluations via $state->withEvaluation()
     * - Return modified state (or original if no changes needed)
     *
     * @param AgentState $state Current state with event context in currentExecution
     * @param HookType $event The event being processed
     * @return AgentState Potentially modified state
     */
    public function process(AgentState $state, HookType $event): AgentState;
}
