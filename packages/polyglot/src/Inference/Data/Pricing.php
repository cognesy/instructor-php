<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use InvalidArgumentException;

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
     *
     * @throws InvalidArgumentException If any pricing value is non-numeric or negative
     */
    public static function fromArray(array $data): self {
        $fields = [
            'input' => ['input', 'inputPerMToken'],
            'output' => ['output', 'outputPerMToken'],
            'cacheRead' => ['cacheRead', 'cacheReadPerMToken'],
            'cacheWrite' => ['cacheWrite', 'cacheWritePerMToken'],
            'reasoning' => ['reasoning', 'reasoningPerMToken'],
        ];

        $validated = [];
        foreach ($fields as $name => $keys) {
            $value = $data[$keys[0]] ?? $data[$keys[1]] ?? null;
            if ($value === null) {
                $validated[$name] = null;
                continue;
            }
            if (!is_numeric($value)) {
                throw new InvalidArgumentException(
                    "Pricing field '{$name}' must be numeric, got: " . gettype($value)
                );
            }
            $floatValue = (float) $value;
            if ($floatValue < 0) {
                throw new InvalidArgumentException(
                    "Pricing field '{$name}' must be non-negative, got: {$floatValue}"
                );
            }
            $validated[$name] = $floatValue;
        }

        $input = $validated['input'] ?? 0.0;

        return new self(
            inputPerMToken: $input,
            outputPerMToken: $validated['output'] ?? 0.0,
            cacheReadPerMToken: $validated['cacheRead'],
            cacheWritePerMToken: $validated['cacheWrite'],
            reasoningPerMToken: $validated['reasoning'],
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
