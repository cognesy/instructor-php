<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Streaming;

use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\Data\Usage;

final class StreamingUsageState
{
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private int $cacheWriteTokens = 0;
    private int $cacheReadTokens = 0;
    private int $reasoningTokens = 0;
    private ?Pricing $pricing = null;
    private bool $isCumulative = false;

    public function apply(?Usage $usage, bool $isCumulative): void
    {
        if ($usage === null) {
            return;
        }

        $this->pricing ??= $usage->pricing();
        $this->isCumulative = $this->isCumulative || $isCumulative;

        if ($usage->total() === 0) {
            return;
        }

        match ($isCumulative) {
            true => $this->applyCumulative($usage),
            false => $this->applyIncremental($usage),
        };
    }

    public function toUsage(): Usage
    {
        return new Usage(
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            cacheWriteTokens: $this->cacheWriteTokens,
            cacheReadTokens: $this->cacheReadTokens,
            reasoningTokens: $this->reasoningTokens,
            pricing: $this->pricing,
        );
    }

    public function inputTokens(): int
    {
        return $this->inputTokens;
    }

    public function outputTokens(): int
    {
        return $this->outputTokens;
    }

    public function cacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }

    public function cacheReadTokens(): int
    {
        return $this->cacheReadTokens;
    }

    public function reasoningTokens(): int
    {
        return $this->reasoningTokens;
    }

    public function pricing(): ?Pricing
    {
        return $this->pricing;
    }

    public function isCumulative(): bool
    {
        return $this->isCumulative;
    }

    private function applyCumulative(Usage $usage): void
    {
        $this->inputTokens = max($this->inputTokens, $usage->inputTokens);
        $this->outputTokens = max($this->outputTokens, $usage->outputTokens);
        $this->cacheWriteTokens = max($this->cacheWriteTokens, $usage->cacheWriteTokens);
        $this->cacheReadTokens = max($this->cacheReadTokens, $usage->cacheReadTokens);
        $this->reasoningTokens = max($this->reasoningTokens, $usage->reasoningTokens);
    }

    private function applyIncremental(Usage $usage): void
    {
        $this->inputTokens += $usage->inputTokens;
        $this->outputTokens += $usage->outputTokens;
        $this->cacheWriteTokens += $usage->cacheWriteTokens;
        $this->cacheReadTokens += $usage->cacheReadTokens;
        $this->reasoningTokens += $usage->reasoningTokens;
    }
}
