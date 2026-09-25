<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use InvalidArgumentException;

final readonly class DecisionPricing
{
    public function __construct(
        public float $inputPerMToken,
        public float $outputPerMToken,
    ) {
        self::assertRate($inputPerMToken, 'inputPerMToken');
        self::assertRate($outputPerMToken, 'outputPerMToken');
    }

    public static function fromArray(array $data): self
    {
        $unknown = array_diff(array_keys($data), ['inputPerMToken', 'outputPerMToken']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown Decision pricing fields: '.implode(', ', $unknown));
        }
        foreach (['inputPerMToken', 'outputPerMToken'] as $field) {
            if (! array_key_exists($field, $data) || (! is_int($data[$field]) && ! is_float($data[$field]))) {
                throw new InvalidArgumentException("Decision pricing requires numeric {$field}.");
            }
        }

        return new self(
            inputPerMToken: (float) $data['inputPerMToken'],
            outputPerMToken: (float) $data['outputPerMToken'],
        );
    }

    /** @return array{inputPerMToken: float, outputPerMToken: float} */
    public function toArray(): array
    {
        return [
            'inputPerMToken' => $this->inputPerMToken,
            'outputPerMToken' => $this->outputPerMToken,
        ];
    }

    private static function assertRate(float $rate, string $field): void
    {
        if (! is_finite($rate) || $rate < 0) {
            throw new InvalidArgumentException("Decision pricing {$field} must be finite and non-negative.");
        }
    }
}
