<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Tools;

use Cognesy\Agents\Tool\Contracts\CanAccessAgentState;
use Cognesy\Agents\Tool\Traits\HasAgentState;

abstract class StateAwareTool extends SimpleTool implements CanAccessAgentState
{
    use HasAgentState;
}
