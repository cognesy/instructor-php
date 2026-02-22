<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use DateTimeImmutable;

final readonly class AgentSessionInfo
{
    public function __construct(
        private SessionId $sessionId,
        private ?SessionId $parentId,
        private SessionStatus $status,
        private int $version,
        private string $agentName,
        private string $agentLabel,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////////

    public static function fresh(
        SessionId $sessionId,
        string $agentName,
        string $agentLabel,
        ?SessionId $parentId = null,
    ): self {
        $now = new DateTimeImmutable();
        return new self(
            sessionId: $sessionId,
            parentId: $parentId,
            status: SessionStatus::Active,
            version: 0,
            agentName: $agentName,
            agentLabel: $agentLabel,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    // ACCESSORS ///////////////////////////////////////////////////

    public function sessionId(): string {
        return $this->sessionId->value;
    }

    public function parentId(): ?string {
        return $this->parentId?->toString();
    }

    public function parentIdValue(): ?SessionId {
        return $this->parentId;
    }

    public function status(): SessionStatus {
        return $this->status;
    }

    public function version(): int {
        return $this->version;
    }

    public function agentName(): string {
        return $this->agentName;
    }

    public function agentLabel(): string {
        return $this->agentLabel;
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    // MUTATORS ////////////////////////////////////////////////////

    public function with(
        ?SessionStatus $status = null,
        ?int $version = null,
        ?DateTimeImmutable $updatedAt = null,
    ): self {
        return new self(
            sessionId: $this->sessionId,
            parentId: $this->parentId,
            status: $status ?? $this->status,
            version: $version ?? $this->version,
            agentName: $this->agentName,
            agentLabel: $this->agentLabel,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt ?? $this->updatedAt,
        );
    }

    public function withParentId(?SessionId $parentId): self {
        return new self(
            sessionId: $this->sessionId,
            parentId: $parentId,
            status: $this->status,
            version: $this->version,
            agentName: $this->agentName,
            agentLabel: $this->agentLabel,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    // SERIALIZATION ///////////////////////////////////////////////

    public function toArray(): array {
        return [
            'sessionId' => $this->sessionId->value,
            'parentId' => $this->parentId?->value,
            'status' => $this->status->value,
            'version' => $this->version,
            'agentName' => $this->agentName,
            'agentLabel' => $this->agentLabel,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        $parentId = match (true) {
            !isset($data['parentId']) => null,
            !is_string($data['parentId']) => null,
            $data['parentId'] === '' => null,
            default => SessionId::from($data['parentId']),
        };

        return new self(
            sessionId: SessionId::from($data['sessionId']),
            parentId: $parentId,
            status: SessionStatus::from($data['status']),
            version: $data['version'],
            agentName: $data['agentName'],
            agentLabel: $data['agentLabel'],
            createdAt: new DateTimeImmutable($data['createdAt']),
            updatedAt: new DateTimeImmutable($data['updatedAt']),
        );
    }
}
