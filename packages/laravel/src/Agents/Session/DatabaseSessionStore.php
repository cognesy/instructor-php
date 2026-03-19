<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Agents\Session;

use Cognesy\Agents\Session\Collections\SessionInfoList;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
use DateTimeImmutable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseSessionStore implements CanStoreSessions
{
    public function __construct(
        private ConnectionResolverInterface $database,
        private ?string $connection = null,
        private string $table = 'instructor_agent_sessions',
    ) {}

    #[\Override]
    public function create(AgentSession $session): AgentSession
    {
        $id = $session->sessionId()->value;
        if ($this->exists($session->sessionId())) {
            throw new SessionConflictException("Session already exists: '{$id}'");
        }

        if ($session->version() !== 0) {
            throw new SessionConflictException("Cannot create session '{$id}' with non-zero version {$session->version()}");
        }

        $persisted = AgentSession::reconstitute($session, 1, new DateTimeImmutable());
        $inserted = $this->query()->insert($this->row($persisted));
        if (!$inserted) {
            throw new SessionConflictException("Failed to create session '{$id}'");
        }

        return $persisted;
    }

    #[\Override]
    public function save(AgentSession $session): AgentSession
    {
        $id = $session->sessionId()->value;
        $persisted = AgentSession::reconstitute($session, $session->version() + 1, new DateTimeImmutable());
        $updated = $this->query()
            ->where('session_id', $id)
            ->where('version', $session->version())
            ->update($this->row($persisted));

        if ($updated === 1) {
            return $persisted;
        }

        if (!$this->exists($session->sessionId())) {
            throw new SessionNotFoundException("Session not found: '{$id}'");
        }

        throw new SessionConflictException("Version conflict for session '{$id}'");
    }

    #[\Override]
    public function load(SessionId $sessionId): ?AgentSession
    {
        $row = $this->query()->where('session_id', $sessionId->value)->first();
        if ($row === null) {
            return null;
        }

        $payload = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);

        return AgentSession::fromArray($payload);
    }

    #[\Override]
    public function exists(SessionId $sessionId): bool
    {
        return $this->query()->where('session_id', $sessionId->value)->exists();
    }

    #[\Override]
    public function delete(SessionId $sessionId): void
    {
        $this->query()->where('session_id', $sessionId->value)->delete();
    }

    #[\Override]
    public function listHeaders(): SessionInfoList
    {
        $rows = $this->query()
            ->orderByDesc('updated_at')
            ->get([
                'session_id',
                'parent_session_id',
                'status',
                'version',
                'agent_name',
                'agent_label',
                'created_at',
                'updated_at',
            ]);

        $headers = [];
        foreach ($rows as $row) {
            $headers[] = AgentSessionInfo::fromArray([
                'sessionId' => $row->session_id,
                'parentId' => $row->parent_session_id,
                'status' => $row->status,
                'version' => (int) $row->version,
                'agentName' => $row->agent_name,
                'agentLabel' => $row->agent_label,
                'createdAt' => $row->created_at,
                'updatedAt' => $row->updated_at,
            ]);
        }

        return new SessionInfoList(...$headers);
    }

    /** @return array<string, mixed> */
    private function row(AgentSession $session): array
    {
        $header = $session->info();

        return [
            'session_id' => $session->sessionId()->value,
            'parent_session_id' => $header->parentId()?->value,
            'status' => $header->status()->value,
            'version' => $session->version(),
            'agent_name' => $header->agentName(),
            'agent_label' => $header->agentLabel(),
            'payload' => json_encode($session->toArray(), JSON_THROW_ON_ERROR),
            'created_at' => $header->createdAt()->format(DateTimeImmutable::ATOM),
            'updated_at' => $header->updatedAt()->format(DateTimeImmutable::ATOM),
        ];
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return $this->connection()->table($this->table);
    }

    private function connection(): ConnectionInterface
    {
        return $this->database->connection($this->connection);
    }
}
