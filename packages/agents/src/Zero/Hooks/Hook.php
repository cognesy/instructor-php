<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Hooks;

use Cognesy\Agents\Core\Data\AgentState;

interface Hook
{
    /**
     * @return list<HookType>
     */
    public function appliesTo(): array;

    public function on(HookType $type, AgentState $state): AgentState;
}
