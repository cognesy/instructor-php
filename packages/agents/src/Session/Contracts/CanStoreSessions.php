<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;

interface CanStoreSessions
{
    public function create(AgentSession $session): AgentSession;
    public function save(AgentSession $session): AgentSession;
    public function load(SessionId $sessionId): ?AgentSession;
    public function exists(SessionId $sessionId): bool;
    public function delete(SessionId $sessionId): void;
    public function listHeaders(): SessionInfoList;
}
