<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Subagents;

/**
 * @deprecated Use SubagentRegistry and SubagentSpec instead. Will be removed in next major version.
 */

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Enums\AgentType;

final class DefaultAgentCapability implements AgentCapability
{
    private const EXPLORE_TOOLS = ['bash', 'read_file'];
    private const CODE_TOOLS = ['bash', 'read_file', 'write_file', 'edit_file', 'todo_write'];
    private const PLAN_TOOLS = ['read_file'];

    private const BLOCKED_TOOLS = ['spawn_subagent'];

    #[\Override]
    public function toolsFor(AgentType $type, Tools $allTools): Tools {
        $allowedNames = $this->allowedToolNames($type);

        $filtered = [];
        foreach ($allTools->all() as $tool) {
            $name = $tool->name();
            if (in_array($name, $allowedNames, true) && !in_array($name, self::BLOCKED_TOOLS, true)) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    #[\Override]
    public function systemPromptFor(AgentType $type): string {
        return $type->systemPromptAddition();
    }

    #[\Override]
    public function isToolAllowed(AgentType $type, string $toolName): bool {
        if (in_array($toolName, self::BLOCKED_TOOLS, true)) {
            return false;
        }

        $allowed = $this->allowedToolNames($type);
        return in_array($toolName, $allowed, true);
    }

    /** @return list<string> */
    private function allowedToolNames(AgentType $type): array {
        return match($type) {
            AgentType::Explore => self::EXPLORE_TOOLS,
            AgentType::Code => self::CODE_TOOLS,
            AgentType::Plan => self::PLAN_TOOLS,
        };
    }
}
