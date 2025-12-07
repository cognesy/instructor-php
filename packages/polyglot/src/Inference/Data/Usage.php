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
    ) {}

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none() : Usage {
        return new self();
    }

    public static function fromArray(array $value) : self {
        return new self(
            inputTokens: (int) ($value['input'] ?? 0),
            outputTokens: (int) ($value['output'] ?? 0),
            cacheWriteTokens: (int) ($value['cacheWrite'] ?? 0),
            cacheReadTokens: (int) ($value['cacheRead'] ?? 0),
            reasoningTokens: (int) ($value['reasoning'] ?? 0),
        );
    }

    public static function copy(Usage $usage) : self {
        return new self(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheWriteTokens: $usage->cacheWriteTokens,
            cacheReadTokens: $usage->cacheReadTokens,
            reasoningTokens: $usage->reasoningTokens,
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

    // MUTATORS ///////////////////////////////////////////////////////////

    public function accumulate(Usage $usage) : self {
        $this->inputTokens = $this->safeAdd($this->inputTokens, $usage->inputTokens, 'inputTokens');
        $this->outputTokens = $this->safeAdd($this->outputTokens, $usage->outputTokens, 'outputTokens');
        $this->cacheWriteTokens = $this->safeAdd($this->cacheWriteTokens, $usage->cacheWriteTokens, 'cacheWriteTokens');
        $this->cacheReadTokens = $this->safeAdd($this->cacheReadTokens, $usage->cacheReadTokens, 'cacheReadTokens');
        $this->reasoningTokens = $this->safeAdd($this->reasoningTokens, $usage->reasoningTokens, 'reasoningTokens');
        return $this;
    }

    /**
     * Safely adds two integers, throwing an exception if the result would overflow
     *
     * @throws \InvalidArgumentException When token counts are unrealistically large
     */
    private function safeAdd(int $a, int $b, string $fieldName): int {
        // Reasonable upper limit for token counts (1 million tokens)
        $maxReasonableTokens = 1_000_000;

        if ($a > $maxReasonableTokens || $b > $maxReasonableTokens) {
            throw new \InvalidArgumentException(
                "Unrealistic token count detected in {$fieldName}: {$a} + {$b}. " .
                "This indicates a bug in token accumulation logic. " .
                "Token counts should not exceed {$maxReasonableTokens}."
            );
        }

        // The overflow check is not needed since we limit tokens to reasonable amounts above

        return $a + $b;
    }

    public function withAccumulated(Usage $usage) : self {
        return (new self(
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens,
        ))->accumulate($usage);
    }

    // TRANSFORMERS ////////////////////////////////////////////////////////

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
        ];
    }

    public function clone() : self {
        return self::copy($this);
    }
}
