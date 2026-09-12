<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Models;

use InvalidArgumentException;

final readonly class ModelLimits
{
    public function __construct(
        public ?int $contextWindow = null,
        public ?int $maxOutput = null,
    ) {
        $this->assertPositiveOrUnknown($contextWindow, 'contextWindow');
        $this->assertPositiveOrUnknown($maxOutput, 'maxOutput');
    }

    public static function fromArray(array $data): self
    {
        ModelRecordFields::validate($data, ['contextWindow', 'maxOutput'], 'limits', ['contextWindow', 'maxOutput']);
        return new self(
            contextWindow: self::nullableInt($data['contextWindow'] ?? null),
            maxOutput: self::nullableInt($data['maxOutput'] ?? null),
        );
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return array_filter([
            'contextWindow' => $this->contextWindow,
            'maxOutput' => $this->maxOutput,
        ], static fn(?int $value): bool => $value !== null);
    }

    private function assertPositiveOrUnknown(?int $value, string $field): void
    {
        if ($value === null || $value > 0) {
            return;
        }

        throw new InvalidArgumentException("{$field} must be positive or null.");
    }

    private static function nullableInt(mixed $value): ?int
    {
        return match (true) {
            $value === null => null,
            is_int($value) => $value,
            default => throw new InvalidArgumentException('Model limit must be an integer or null.'),
        };
    }
}
