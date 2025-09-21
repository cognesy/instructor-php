<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State;

use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final readonly class StateInfo
{
    public function __construct(
        private string $id,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function new(): self {
        $now = new DateTimeImmutable();
        return new self(Uuid::uuid4(), $now, $now);
    }

    public function id(): string {
        return $this->id;
    }

    public function startedAt(): DateTimeImmutable {
        return $this->startedAt;
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->updatedAt;
    }

    public function touch(): self {
        return new self($this->id, $this->startedAt, new DateTimeImmutable());
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? Uuid::uuid4(),
            new DateTimeImmutable($data['startedAt'] ?? 'now'),
            new DateTimeImmutable($data['updatedAt'] ?? 'now'),
        );
    }
}