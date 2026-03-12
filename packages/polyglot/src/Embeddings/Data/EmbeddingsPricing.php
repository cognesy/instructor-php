<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

use InvalidArgumentException;

/**
 * Pricing configuration for embeddings token costs.
 * Price is in USD per 1,000,000 tokens (per 1M tokens).
 */
final class EmbeddingsPricing
{
    public function __construct(
        public readonly float $inputPerMToken = 0.0,
    ) {}

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none(): self
    {
        return new self();
    }

    /**
     * @throws InvalidArgumentException If pricing value is non-numeric or negative
     */
    public static function fromArray(array $data): self
    {
        $value = $data['input'] ?? $data['inputPerMToken'] ?? 0.0;

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                "Pricing field 'input' must be numeric, got: " . gettype($value)
            );
        }

        $floatValue = (float) $value;
        if ($floatValue < 0) {
            throw new InvalidArgumentException(
                "Pricing field 'input' must be non-negative, got: {$floatValue}"
            );
        }

        return new self(inputPerMToken: $floatValue);
    }

    // ACCESSORS //////////////////////////////////////////////////////////

    public function hasAnyPricing(): bool
    {
        return $this->inputPerMToken > 0.0;
    }

    // TRANSFORMERS ///////////////////////////////////////////////////////

    public function toArray(): array
    {
        return [
            'input' => $this->inputPerMToken,
        ];
    }
}
