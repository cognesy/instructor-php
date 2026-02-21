<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\SaveResult;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;

interface CanStoreSessions
{
    public function save(AgentSession $session): SaveResult;
    public function load(SessionId $sessionId): ?AgentSession;
    public function exists(SessionId $sessionId): bool;
    public function delete(SessionId $sessionId): void;
    public function listHeaders(): SessionInfoList;
}
