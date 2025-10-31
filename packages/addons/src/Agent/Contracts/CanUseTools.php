<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;

interface CanUseTools
{
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep;
}
