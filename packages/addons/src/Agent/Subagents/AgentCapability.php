<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Subagents;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Enums\AgentType;

interface AgentCapability
{
    /**
     * Get the tools available to an agent of the given type.
     */
    public function toolsFor(AgentType $type, Tools $allTools): Tools;

    /**
     * Get the system prompt addition for an agent of the given type.
     */
    public function systemPromptFor(AgentType $type): string;

    /**
     * Check if the given tool is allowed for the agent type.
     */
    public function isToolAllowed(AgentType $type, string $toolName): bool;
}
