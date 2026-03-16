<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Collections\SessionInfoList;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;

interface CanManageAgentSessions
{
    public function create(AgentDefinition $definition, ?AgentState $seed = null): AgentSession;
    public function listSessions(): SessionInfoList;
    public function getSessionInfo(SessionId $sessionId): AgentSessionInfo;
    public function getSession(SessionId $sessionId): AgentSession;
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession;
}
