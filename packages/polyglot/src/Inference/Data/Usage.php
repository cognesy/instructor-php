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
        $this->inputTokens = (int) ($this->inputTokens + (int) $usage->inputTokens);
        $this->outputTokens = (int) ($this->outputTokens + (int) $usage->outputTokens);
        $this->cacheWriteTokens = (int) ($this->cacheWriteTokens + (int) $usage->cacheWriteTokens);
        $this->cacheReadTokens = (int) ($this->cacheReadTokens + (int) $usage->cacheReadTokens);
        $this->reasoningTokens = (int) ($this->reasoningTokens + (int) $usage->reasoningTokens);
        return $this;
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
