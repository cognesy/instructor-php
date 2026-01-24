<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Adapters;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Hooks\Contracts\Hook;
use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Data\HookEvent;
use Cognesy\Agents\Agent\Hooks\Data\HookOutcome;
use Cognesy\Agents\Agent\Hooks\Data\StepHookContext;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;

/**
 * Adapter that wraps existing StateProcessor to work with the new Hook system.
 *
 * This adapter allows existing CanProcessAnyState implementations to be used
 * with the unified HookStack while maintaining backward compatibility.
 *
 * The adapter:
 * - Only processes StepHookContext (step events)
 * - Processes either BeforeStep or AfterStep based on configuration
 * - Preserves the original processor behavior
 *
 * @example
 * // Wrap existing processor for after-step processing
 * $processor = new AppendStepMessages();
 * $hook = new StateProcessorAdapter($processor, 'after');
 *
 * // Use with HookStack
 * $stack = new HookStack();
 * $stack = $stack->with($hook);
 */
final readonly class StateProcessorAdapter implements Hook
{
    /**
     * @param CanProcessAgentState $processor The processor to adapt
     * @param 'before'|'after' $position When to run: 'before' or 'after' step
     */
    public function __construct(
        private CanProcessAgentState $processor,
        private string               $position = 'after',
    ) {}

    #[\Override]
    public function handle(HookContext $context, callable $next): HookOutcome
    {
        // Only process step events
        if (!$context instanceof StepHookContext) {
            return $next($context);
        }

        // Check if this is the right event type for this processor
        $targetEvent = $this->position === 'before'
            ? HookEvent::BeforeStep
            : HookEvent::AfterStep;

        if ($context->eventType() !== $targetEvent) {
            return $next($context);
        }

        $state = $context->state();

        // Check if processor can handle this state
        if (!$this->processor->canProcess($state)) {
            return $next($context);
        }

        // Process the state
        // The processor's next callback should process remaining processors
        // We wrap our next to match the processor's expected signature

        $processorNext = function (AgentState $state) use ($context, $next): AgentState {
            // Update context with new state
            $newContext = $context->withState($state);

            // Continue the hook chain
            $outcome = $next($newContext);

            // Extract the final state from the outcome
            $finalContext = $outcome->context() ?? $newContext;

            return $finalContext->state();
        };

        $newState = $this->processor->process($state, $processorNext);

        // Return with the processed state
        return HookOutcome::proceed($context->withState($newState));
    }
}
