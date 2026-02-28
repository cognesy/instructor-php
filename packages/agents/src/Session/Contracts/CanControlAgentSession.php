<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Enums\AgentSessionStage;

interface CanControlAgentSession
{
    public function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession;
}
