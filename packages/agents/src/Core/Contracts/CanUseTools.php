<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;

interface CanUseTools
{
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep;
}
