<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;

final readonly class SessionRepository
{
    public function __construct(
        private CanStoreSessions $store,
    ) {}

    public function load(SessionId $sessionId): AgentSession {
        $session = $this->store->load($sessionId);
        if ($session === null) {
            throw new SessionNotFoundException("Session not found: {$sessionId->value}");
        }
        return $session;
    }

    public function save(AgentSession $session): SaveResult {
        return $this->store->save($session);
    }

    public function exists(SessionId $sessionId): bool {
        return $this->store->exists($sessionId);
    }

    public function delete(SessionId $sessionId): void {
        $this->store->delete($sessionId);
    }

    public function listHeaders(): SessionInfoList {
        return $this->store->listHeaders();
    }
}
