<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Profiler\TracksObjectCreation;

class InferenceUsage
{
    use TracksObjectCreation;

    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $cacheReadTokens = 0,
        public int $reasoningTokens = 0,
    ) {
        $this->trackObjectCreation();
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////////

    public static function none() : InferenceUsage {
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

    public function withAccumulated(InferenceUsage $usage) : self {
        return new self(
            inputTokens: $this->inputTokens + $usage->inputTokens,
            outputTokens: $this->outputTokens + $usage->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens + $usage->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens + $usage->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens + $usage->reasoningTokens,
        );
    }

    public function with(
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        ?int $cacheWriteTokens = null,
        ?int $cacheReadTokens = null,
        ?int $reasoningTokens = null,
    ) : self {
        return new self(
            inputTokens: $inputTokens ?? $this->inputTokens,
            outputTokens: $outputTokens ?? $this->outputTokens,
            cacheWriteTokens: $cacheWriteTokens ?? $this->cacheWriteTokens,
            cacheReadTokens: $cacheReadTokens ?? $this->cacheReadTokens,
            reasoningTokens: $reasoningTokens ?? $this->reasoningTokens,
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
        ];
    }
}
