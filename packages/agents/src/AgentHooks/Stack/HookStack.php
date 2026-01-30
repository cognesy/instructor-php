<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Stack;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Guards\Contracts\CanStartExecution;
use Cognesy\Agents\Core\Data\AgentState;
use DateTimeImmutable;

/**
 * Priority-based stack for processing hooks.
 *
 * The HookStack manages a collection of hooks and executes them in priority order.
 * Higher priority hooks run first, allowing them to short-circuit or modify state
 * before lower priority hooks run.
 *
 * Hooks are executed sequentially (not middleware-style):
 * - Each hook receives the current AgentState
 * - Each hook returns a (potentially modified) AgentState
 * - State flows from one hook to the next
 *
 * Priority guidelines:
 * - 100+: Security/validation hooks (run first, can forbid continuation)
 * - 0: Normal hooks (default priority)
 * - -100: Logging/monitoring hooks (run last, observe final state)
 *
 * @example
 * $stack = new HookStack();
 * $stack = $stack
 *     ->with(new StepsLimitHook(maxSteps: 10), priority: 100)  // Runs first
 *     ->with(new LoggingHook(), priority: -100);              // Runs last
 *
 * $state = $stack->process($state, HookType::BeforeStep);
 */
final class HookStack
{
    /** @var array<array{hook: Hook, priority: int, order: int}> */
    private array $hooks = [];

    /** @var int Counter for stable sorting when priorities are equal */
    private int $registrationOrder = 0;

    /**
     * Create a new stack with the given hook added.
     *
     * @param Hook $hook The hook to add
     * @param int $priority Higher priority = runs earlier. Default is 0.
     * @return self A new stack instance with the hook added
     */
    public function with(Hook $hook, int $priority = 0): self
    {
        $new = clone $this;
        $new->hooks[] = [
            'hook' => $hook,
            'priority' => $priority,
            'order' => $new->registrationOrder++,
        ];
        return $new;
    }

    /**
     * Add multiple hooks at once.
     *
     * @param array<Hook|array{hook: Hook, priority?: int}> $hooks Hooks to add
     * @return self A new stack instance with all hooks added
     */
    public function withAll(array $hooks): self
    {
        $new = $this;
        foreach ($hooks as $entry) {
            if ($entry instanceof Hook) {
                $new = $new->with($entry);
            } else {
                $hook = $entry['hook'];
                $priority = $entry['priority'] ?? 0;
                $new = $new->with($hook, $priority);
            }
        }
        return $new;
    }

    /**
     * Process state through all applicable hooks for the given event.
     *
     * Hooks are executed in priority order (highest first). Each hook
     * that handles this event type receives the state from the previous
     * hook and returns a potentially modified state.
     *
     * Flow control is via evaluations: hooks write evaluations to state,
     * and the AgentLoop aggregates them into ContinuationOutcome.
     *
     * @param AgentState $state The state to process
     * @param HookType $event The event type being processed
     * @return AgentState The state after all hooks have processed
     */
    public function process(AgentState $state, HookType $event): AgentState
    {
        $sorted = $this->getSortedHooks();

        foreach ($sorted as $entry) {
            /** @var Hook $hook */
            $hook = $entry['hook'];

            // Skip hooks that don't handle this event type
            if (!in_array($event, $hook->appliesTo(), true)) {
                continue;
            }

            // Process and update state
            $state = $hook->process($state, $event);
        }

        return $state;
    }

    /**
     * Notify all hooks that implement CanStartExecution that execution has started.
     *
     * This is used to signal time-based guards and other stateful hooks.
     *
     * @param DateTimeImmutable $startedAt When execution began
     */
    public function executionStarted(DateTimeImmutable $startedAt): void
    {
        foreach ($this->hooks as $entry) {
            $hook = $entry['hook'];
            if ($hook instanceof CanStartExecution) {
                $hook->executionStarted($startedAt);
            }
        }
    }

    /**
     * Check if the stack is empty.
     */
    public function isEmpty(): bool
    {
        return $this->hooks === [];
    }

    /**
     * Get the number of hooks in the stack.
     */
    public function count(): int
    {
        return count($this->hooks);
    }

    /**
     * Get hooks sorted by priority (descending) and registration order (ascending).
     *
     * @return array<array{hook: Hook, priority: int, order: int}>
     */
    private function getSortedHooks(): array
    {
        $sorted = $this->hooks;

        usort($sorted, static function (array $a, array $b): int {
            // Higher priority first
            $priorityCompare = $b['priority'] <=> $a['priority'];
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }
            // Same priority: earlier registration first (stable sort)
            return $a['order'] <=> $b['order'];
        });

        return $sorted;
    }
}
