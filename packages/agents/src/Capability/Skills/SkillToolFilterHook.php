<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

/**
 * Enforces allowed-tools restrictions when a skill with allowed-tools is active.
 *
 * Triggers on BeforeToolUse. Checks AgentState metadata for an active skill's
 * allowed-tools list. If the tool being called is not in the list, blocks execution.
 *
 * The load_skill tool itself is never blocked.
 */
final readonly class SkillToolFilterHook implements HookInterface
{
    public const META_KEY = 'active_skill_allowed_tools';

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $allowedTools = $context->state()->metadata()->get(self::META_KEY);
        if (!is_array($allowedTools) || $allowedTools === []) {
            return $context;
        }

        $toolName = $context->toolCall()?->name();
        if ($toolName === null) {
            return $context;
        }

        // Never block the load_skill tool itself
        if ($toolName === 'load_skill') {
            return $context;
        }

        if (!in_array($toolName, $allowedTools, true)) {
            return $context->withToolExecutionBlocked(
                "Tool '{$toolName}' is not allowed by the active skill. Allowed tools: " . implode(', ', $allowedTools),
            );
        }

        return $context;
    }
}
