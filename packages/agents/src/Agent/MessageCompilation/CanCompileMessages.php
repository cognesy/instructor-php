<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\MessageCompilation;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

/**
 * Compile a message view from agent state.
 */
interface CanCompileMessages
{
    public function compile(AgentState $state): Messages;
}
