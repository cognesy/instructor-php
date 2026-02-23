<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Contracts;

use Cognesy\Agents\Session\Collections\SessionInfoList;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\SessionId;

interface CanStoreSessions
{
    public function create(AgentSession $session): AgentSession;
    public function save(AgentSession $session): AgentSession;
    public function load(SessionId $sessionId): ?AgentSession;
    public function exists(SessionId $sessionId): bool;
    public function delete(SessionId $sessionId): void;
    public function listHeaders(): SessionInfoList;
}
