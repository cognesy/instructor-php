<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Pricing;

/**
 * Universal cost value object — the result of any usage cost calculation.
 * All values are in USD. Breakdown keys are service-specific
 * (e.g. 'input', 'output', 'cacheRead' for inference; 'input' for embeddings).
 */
final readonly class Cost
{
    /**
     * @param float $total Total cost in USD (rounded to 6 decimal places)
     * @param array<string, float> $breakdown Per-category cost breakdown in USD
     */
    public function __construct(
        public float $total,
        public array $breakdown = [],
    ) {}

    public static function none(): self
    {
        return new self(0.0);
    }

    public function withAccumulated(Cost $other): self
    {
        $merged = $this->breakdown;
        foreach ($other->breakdown as $key => $value) {
            $merged[$key] = ($merged[$key] ?? 0.0) + $value;
        }

        return new self(
            total: round($this->total + $other->total, 6),
            breakdown: $merged,
        );
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'breakdown' => $this->breakdown,
        ];
    }

    public function toString(): string
    {
        return sprintf('$%.6f', $this->total);
    }
}
