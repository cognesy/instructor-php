<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers;

use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;

interface CanUseTools
{
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentState;
}
