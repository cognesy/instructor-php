<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Data;

use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Transient execution context for the currently running step.
 */
final readonly class CurrentExecution
{
    public string $id;

    public function __construct(
        public int $stepNumber,
        public DateTimeImmutable $startedAt = new DateTimeImmutable(),
        string $id = '',
    ) {
        $this->id = $id !== '' ? $id : Uuid::uuid4();
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'stepNumber' => $this->stepNumber,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        $stepNumberValue = $data['stepNumber'] ?? null;
        $stepNumber = is_int($stepNumberValue) ? $stepNumberValue : (int) $stepNumberValue;

        $startedAtValue = $data['startedAt'] ?? null;
        $startedAt = match (true) {
            $startedAtValue instanceof DateTimeImmutable => $startedAtValue,
            is_string($startedAtValue) && $startedAtValue !== '' => new DateTimeImmutable($startedAtValue),
            default => new DateTimeImmutable(),
        };

        $idValue = $data['id'] ?? '';
        $id = is_string($idValue) ? $idValue : '';

        return new self(
            stepNumber: $stepNumber,
            startedAt: $startedAt,
            id: $id,
        );
    }
}
