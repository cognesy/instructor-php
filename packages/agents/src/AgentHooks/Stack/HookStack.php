<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Stack;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Contracts\HookContext;
use Cognesy\Agents\AgentHooks\Data\HookOutcome;

/**
 * Priority-based stack for processing hooks.
 *
 * The HookStack manages a collection of hooks and executes them in priority order.
 * Higher priority hooks run first (wrap outer), giving them the ability to:
 * - Intercept and short-circuit before lower priority hooks run
 * - Wrap lower priority hooks with before/after behavior
 *
 * Priority guidelines:
 * - 100+: Security/validation hooks (run first, can block)
 * - 0: Normal hooks (default priority)
 * - -100: Logging/monitoring hooks (run last, observe final state)
 *
 * @example
 * $stack = new HookStack();
 * $stack = $stack
 *     ->with(new SecurityHook(), priority: 100)  // Runs first
 *     ->with(new LoggingHook(), priority: -100); // Runs last
 *
 * $outcome = $stack->process($context, fn($ctx) => HookOutcome::proceed($ctx));
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
     * @param int $priority Higher priority = runs earlier (wraps outer). Default is 0.
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
     * Process a context through all hooks in the stack.
     *
     * @param HookContext $context The context to process
     * @param callable(HookContext): HookOutcome $terminal The final handler (terminal)
     * @return HookOutcome The result of hook processing
     */
    public function process(HookContext $context, callable $terminal): HookOutcome
    {
        $chain = $this->buildChain($terminal);
        return $chain($context);
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
     * Build the hook chain with priority ordering.
     *
     * @param callable(HookContext): HookOutcome $terminal
     * @return callable(HookContext): HookOutcome
     */
    private function buildChain(callable $terminal): callable
    {
        $next = $terminal;

        // Sort by priority descending, then by registration order ascending for stable sort
        $sorted = $this->getSortedHooks();

        // Build chain in reverse order so higher priority wraps outer
        foreach (array_reverse($sorted) as $entry) {
            $currentNext = $next;
            $hook = $entry['hook'];
            $next = static fn(HookContext $context): HookOutcome
                => $hook->handle($context, $currentNext);
        }

        return $next;
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
