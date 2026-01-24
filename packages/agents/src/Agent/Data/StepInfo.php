<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Data;

use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final readonly class StepInfo
{
    public function __construct(
        private string $id,
        private DateTimeImmutable $createdAt,
    ) {}

    public static function new(): self {
        return new self(Uuid::uuid4(), new DateTimeImmutable());
    }

    public function id(): string {
        return $this->id;
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? Uuid::uuid4(),
            new DateTimeImmutable($data['createdAt'] ?? 'now'),
        );
    }
}