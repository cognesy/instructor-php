<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers;

use Cognesy\Agents\Data\AgentState;

interface CanUseTools
{
    public function useTools(AgentState $state): AgentState;
}
