<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $cacheReadTokens = 0,
        public int $reasoningTokens = 0,
        public ?Pricing $pricing = null,
    ) {}

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none() : Usage {
        return new self();
    }

    public static function fromArray(array $value) : self {
        $hasPricing = array_key_exists('pricing', $value);
        $pricingValue = $value['pricing'] ?? null;

        return new self(
            inputTokens: (int) ($value['input'] ?? 0),
            outputTokens: (int) ($value['output'] ?? 0),
            cacheWriteTokens: (int) ($value['cacheWrite'] ?? 0),
            cacheReadTokens: (int) ($value['cacheRead'] ?? 0),
            reasoningTokens: (int) ($value['reasoning'] ?? 0),
            pricing: match (true) {
                !$hasPricing => null,
                is_array($pricingValue) => Pricing::fromArray($pricingValue),
                default => null,
            },
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////
    public function total() : int {
        return $this->inputTokens
            + $this->outputTokens
            + $this->cacheWriteTokens
            + $this->cacheReadTokens
            + $this->reasoningTokens;
    }

    public function input() : int {
        return $this->inputTokens;
    }

    public function output() : int {
        return $this->outputTokens
            + $this->reasoningTokens;
    }

    public function cache() : int {
        return $this->cacheWriteTokens
            + $this->cacheReadTokens;
    }

    public function pricing(): ?Pricing {
        return $this->pricing;
    }

    /**
     * Calculate total cost in USD.
     * Uses stored pricing if no argument provided.
     * Pricing is in $/1M tokens.
     *
     * @throws \RuntimeException If no pricing available
     */
    public function cost(?Pricing $pricing = null): float {
        $pricing = $pricing ?? $this->pricing;

        if ($pricing === null) {
            throw new \RuntimeException(
                'Cannot calculate cost: no pricing information available. ' .
                'Either pass Pricing to calculateCost() or configure pricing in LLMConfig.'
            );
        }

        // Pricing is per 1M tokens, so divide by 1_000_000
        $cost = ($this->inputTokens / 1_000_000) * $pricing->inputPerMToken
            + ($this->outputTokens / 1_000_000) * $pricing->outputPerMToken
            + ($this->cacheReadTokens / 1_000_000) * $pricing->cacheReadPerMToken
            + ($this->cacheWriteTokens / 1_000_000) * $pricing->cacheWritePerMToken
            + ($this->reasoningTokens / 1_000_000) * $pricing->reasoningPerMToken;

        return round($cost, 6);
    }

    // MUTATORS ///////////////////////////////////////////////////////////

    public function withAccumulated(Usage $usage) : self {
        return new self(
            inputTokens: $this->inputTokens + $usage->inputTokens,
            outputTokens: $this->outputTokens + $usage->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens + $usage->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens + $usage->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens + $usage->reasoningTokens,
            pricing: $this->pricing ?? $usage->pricing,
        );
    }

    public function withPricing(Pricing $pricing): self {
        return $this->with(pricing: $pricing);
    }

    public function with(
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?int $cacheWriteTokens = null,
        ?int $cacheReadTokens = null,
        ?int $reasoningTokens = null,
        ?Pricing $pricing = null,
    ) : self {
        return new self(
            inputTokens: $inputTokens ?? $this->inputTokens,
            outputTokens: $outputTokens ?? $this->outputTokens,
            cacheWriteTokens: $cacheWriteTokens ?? $this->cacheWriteTokens,
            cacheReadTokens: $cacheReadTokens ?? $this->cacheReadTokens,
            reasoningTokens: $reasoningTokens ?? $this->reasoningTokens,
            pricing: $pricing ?? $this->pricing,
        );
    }

    // SERIALIZATION ///////////////////////////////////////////////////////

    public function toString() : string {
        return "Tokens: {$this->total()} (i:{$this->inputTokens} o:{$this->outputTokens} c:{$this->cache()} r:{$this->reasoningTokens})";
    }

    public function toArray() : array {
        return [
            'input' => $this->inputTokens,
            'output' => $this->outputTokens,
            'cacheWrite' => $this->cacheWriteTokens,
            'cacheRead' => $this->cacheReadTokens,
            'reasoning' => $this->reasoningTokens,
            'pricing' => $this->pricing?->toArray(),
        ];
    }
}
