<?php

namespace Cognesy\Polyglot\LLM\Data;

class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheWriteTokens = 0,
        public int $cacheReadTokens = 0,
        public int $reasoningTokens = 0,
    ) {}

    public static function none() : Usage {
        return new Usage();
    }

    public static function fromArray(array $value) : static {
        return new Usage(
            $value['input'] ?? 0,
            $value['output'] ?? 0,
            $value['cacheWrite'] ?? 0,
            $value['cacheRead'] ?? 0,
            $value['reasoning'] ?? 0,
        );
    }

    public static function copy(Usage $usage) : static {
        return new Usage(
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheWriteTokens: $usage->cacheWriteTokens,
            cacheReadTokens: $usage->cacheReadTokens,
            reasoningTokens: $usage->reasoningTokens,
        );
    }

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

    public function accumulate(Usage $usage) : void {
        $this->inputTokens += $usage->inputTokens;
        $this->outputTokens += $usage->outputTokens;
        $this->cacheWriteTokens += $usage->cacheWriteTokens;
        $this->cacheReadTokens += $usage->cacheReadTokens;
        $this->reasoningTokens += $usage->reasoningTokens;
    }

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
