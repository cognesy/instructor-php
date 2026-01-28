<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

/**
 * Pricing configuration for LLM token costs.
 * All prices are in USD per 1,000,000 tokens (per 1M tokens).
 */
final class Pricing
{
    public readonly float $inputPerMToken;
    public readonly float $outputPerMToken;
    public readonly float $cacheReadPerMToken;
    public readonly float $cacheWritePerMToken;
    public readonly float $reasoningPerMToken;

    public function __construct(
        float $inputPerMToken = 0.0,
        float $outputPerMToken = 0.0,
        ?float $cacheReadPerMToken = null,
        ?float $cacheWritePerMToken = null,
        ?float $reasoningPerMToken = null,
    ) {
        $this->inputPerMToken = $inputPerMToken;
        $this->outputPerMToken = $outputPerMToken;
        // Default to input price if not specified
        $this->cacheReadPerMToken = $cacheReadPerMToken ?? $inputPerMToken;
        $this->cacheWritePerMToken = $cacheWritePerMToken ?? $inputPerMToken;
        $this->reasoningPerMToken = $reasoningPerMToken ?? $inputPerMToken;
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none(): self {
        return new self();
    }

    /**
     * Create from array with prices in $/1M tokens.
     * Keys: input, output, cacheRead, cacheWrite, reasoning
     * If cacheRead, cacheWrite, or reasoning are not specified, they default to input price.
     */
    public static function fromArray(array $data): self {
        $input = (float) ($data['input'] ?? $data['inputPerMToken'] ?? 0.0);
        return new self(
            inputPerMToken: $input,
            outputPerMToken: (float) ($data['output'] ?? $data['outputPerMToken'] ?? 0.0),
            cacheReadPerMToken: isset($data['cacheRead']) || isset($data['cacheReadPerMToken'])
                ? (float) ($data['cacheRead'] ?? $data['cacheReadPerMToken'])
                : null,
            cacheWritePerMToken: isset($data['cacheWrite']) || isset($data['cacheWritePerMToken'])
                ? (float) ($data['cacheWrite'] ?? $data['cacheWritePerMToken'])
                : null,
            reasoningPerMToken: isset($data['reasoning']) || isset($data['reasoningPerMToken'])
                ? (float) ($data['reasoning'] ?? $data['reasoningPerMToken'])
                : null,
        );
    }

    // ACCESSORS //////////////////////////////////////////////////////////

    public function hasAnyPricing(): bool {
        return $this->inputPerMToken > 0.0
            || $this->outputPerMToken > 0.0;
    }

    // TRANSFORMERS ///////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'input' => $this->inputPerMToken,
            'output' => $this->outputPerMToken,
            'cacheRead' => $this->cacheReadPerMToken,
            'cacheWrite' => $this->cacheWritePerMToken,
            'reasoning' => $this->reasoningPerMToken,
        ];
    }
}
