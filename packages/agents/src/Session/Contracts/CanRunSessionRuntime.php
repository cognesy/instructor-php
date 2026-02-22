<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;

interface CanRunSessionRuntime
{
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession;
    public function getSession(SessionId $sessionId): AgentSession;
    public function getSessionInfo(SessionId $sessionId): AgentSessionInfo;
    public function listSessions(): SessionInfoList;
}
