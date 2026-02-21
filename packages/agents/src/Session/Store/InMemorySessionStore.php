<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Store;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SaveResult;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use DateTimeImmutable;

final class InMemorySessionStore implements CanStoreSessions
{
    /** @var array<string, array<string, mixed>> */
    private array $payloads = [];

    #[\Override]
    public function save(AgentSession $session): SaveResult
    {
        $id = $session->sessionId();
        $stored = $this->payloads[$id] ?? null;

        if ($stored === null) {
            if ($session->version() !== 0) {
                return SaveResult::conflict("Version conflict for session '{$id}'");
            }

            $persisted = AgentSession::reconstitute($session, 1, new DateTimeImmutable());
            $this->payloads[$id] = $persisted->toArray();
            return SaveResult::ok($persisted);
        }

        $storedVersion = $stored['header']['version'] ?? 0;
        if ($storedVersion !== $session->version()) {
            return SaveResult::conflict("Version conflict for session '{$id}'");
        }

        $persisted = AgentSession::reconstitute($session, $storedVersion + 1, new DateTimeImmutable());
        $this->payloads[$id] = $persisted->toArray();
        return SaveResult::ok($persisted);
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
