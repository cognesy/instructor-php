<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step;

use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final readonly class StepInfo
{
    public function __construct(
        private StepId $id,
        private DateTimeImmutable $createdAt,
    ) {}

    public static function new(): self {
        return new self(StepId::from(Uuid::uuid4()), new DateTimeImmutable());
    }

    public function id(): StepId {
        return $this->id;
    }

    public function createdAt(): DateTimeImmutable {
        return $this->createdAt;
    }

    public function toArray(): array {
        return [
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            StepId::from($data['id'] ?? Uuid::uuid4()),
            new DateTimeImmutable($data['createdAt'] ?? 'now'),
        );
    }
}
