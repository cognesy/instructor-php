<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Contracts;

use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;

interface CanUseTools
{
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep;
}
