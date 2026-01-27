<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Contracts;

/**
 * Interface for matching hook contexts for conditional hook execution.
 *
 * Matchers allow hooks to be selectively applied based on event type,
 * tool name, state conditions, or custom logic. They are composable
 * and reusable across different hook types.
 *
 * @example
 * // Match specific tools
 * $matcher = new ToolNameMatcher('bash');
 *
 * // Match multiple events
 * $matcher = new EventTypeMatcher(HookEvent::PreToolUse, HookEvent::PostToolUse);
 *
 * // Combine matchers
 * $matcher = CompositeMatcher::and(
 *     new ToolNameMatcher('bash'),
 *     new CallableMatcher(fn($ctx) => $ctx->state()->stepCount() < 5),
 * );
 */
interface HookMatcher
{
    /**
     * Determine if this matcher matches the given hook context.
     *
     * @param HookContext $context The context to match against
     * @return bool True if the matcher matches, false otherwise
     */
    public function matches(HookContext $context): bool;
}
