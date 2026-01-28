<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Session-level identity and timing information.
 *
 * This is distinct from execution-level data - sessions can span multiple
 * executions while preserving identity and accumulated timing.
 */
final readonly class SessionInfo
{
    public function __construct(
        private string $id,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function new(): self
    {
        $now = new DateTimeImmutable();
        return new self(
            id: Uuid::uuid4(),
            startedAt: $now,
            updatedAt: $now,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Update the session's updatedAt timestamp.
     */
    public function touch(): self
    {
        return new self(
            id: $this->id,
            startedAt: $this->startedAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self
    {
        $idValue = $data['id'] ?? null;
        $id = is_string($idValue) && $idValue !== '' ? $idValue : Uuid::uuid4();

        $startedAtValue = $data['startedAt'] ?? null;
        $startedAt = match (true) {
            $startedAtValue instanceof DateTimeImmutable => $startedAtValue,
            is_string($startedAtValue) && $startedAtValue !== '' => new DateTimeImmutable($startedAtValue),
            default => new DateTimeImmutable(),
        };

        $updatedAtValue = $data['updatedAt'] ?? null;
        $updatedAt = match (true) {
            $updatedAtValue instanceof DateTimeImmutable => $updatedAtValue,
            is_string($updatedAtValue) && $updatedAtValue !== '' => new DateTimeImmutable($updatedAtValue),
            default => $startedAt,
        };

        return new self(
            id: $id,
            startedAt: $startedAt,
            updatedAt: $updatedAt,
        );
    }
}
