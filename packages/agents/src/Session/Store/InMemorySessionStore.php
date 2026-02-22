<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Store;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
use DateTimeImmutable;

final class InMemorySessionStore implements CanStoreSessions
{
    /** @var array<string, array<string, mixed>> */
    private array $payloads = [];

    #[\Override]
    public function create(AgentSession $session): AgentSession
    {
        $id = $session->sessionId();
        $stored = $this->payloads[$id] ?? null;

        if ($stored !== null) {
            throw new SessionConflictException("Session already exists: '{$id}'");
        }

        if ($session->version() !== 0) {
            throw new SessionConflictException("Cannot create session '{$id}' with non-zero version {$session->version()}");
        }

        $persisted = AgentSession::reconstitute($session, 1, new DateTimeImmutable());
        $this->payloads[$id] = $persisted->toArray();
        return $persisted;
    }

    #[\Override]
    public function save(AgentSession $session): AgentSession
    {
        $id = $session->sessionId();
        $stored = $this->payloads[$id] ?? null;

        if ($stored === null) {
            throw new SessionNotFoundException("Session not found: '{$id}'");
        }

        $storedVersion = $stored['header']['version'] ?? 0;
        if ($storedVersion !== $session->version()) {
            throw new SessionConflictException("Version conflict for session '{$id}'");
        }

        $persisted = AgentSession::reconstitute($session, $storedVersion + 1, new DateTimeImmutable());
        $this->payloads[$id] = $persisted->toArray();
        return $persisted;
    }

    #[\Override]
    public function load(SessionId $sessionId): ?AgentSession
    {
        $key = $sessionId->value;
        $payload = $this->payloads[$key] ?? null;
        return $payload !== null ? AgentSession::fromArray($payload) : null;
    }

    #[\Override]
    public function exists(SessionId $sessionId): bool
    {
        return isset($this->payloads[$sessionId->value]);
    }

    #[\Override]
    public function delete(SessionId $sessionId): void
    {
        unset($this->payloads[$sessionId->value]);
    }

    #[\Override]
    public function listHeaders(): SessionInfoList
    {
        $headers = [];
        foreach ($this->payloads as $payload) {
            $headers[] = AgentSessionInfo::fromArray($payload['header']);
        }
        return new SessionInfoList(...$headers);
    }

}
