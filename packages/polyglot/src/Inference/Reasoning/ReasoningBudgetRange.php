<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

use InvalidArgumentException;

/** Inclusive provider reasoning-token budget range. */
final readonly class ReasoningBudgetRange
{
    public function __construct(
        public int $minimum,
        public ?int $maximum = null,
    ) {
        if ($minimum < 1 || ($maximum !== null && $maximum < $minimum)) {
            throw new InvalidArgumentException('Reasoning budget range must be positive and ordered.');
        }
    }

    public function contains(int $budgetTokens): bool {
        return $budgetTokens >= $this->minimum
            && ($this->maximum === null || $budgetTokens <= $this->maximum);
    }

    public static function fromArray(array $data): self {
        $minimum = $data['min'] ?? null;
        $maximum = $data['max'] ?? null;
        if (!is_int($minimum) || ($maximum !== null && !is_int($maximum))) {
            throw new InvalidArgumentException('Reasoning budget requires integer min and optional max.');
        }

        return new self($minimum, $maximum);
    }

    /** @return array{min: int, max?: int} */
    public function toArray(): array {
        if ($this->maximum === null) {
            return ['min' => $this->minimum];
        }

        return ['min' => $this->minimum, 'max' => $this->maximum];
    }
}
