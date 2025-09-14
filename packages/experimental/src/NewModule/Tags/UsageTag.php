<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule\Tags;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

/**
 * UsageTag - Token usage and cost information for LLM calls
 */
final readonly class UsageTag implements TagInterface
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public float $cost = 0.0
    ) {
        if ($inputTokens < 0) {
            throw new \InvalidArgumentException('Input tokens cannot be negative');
        }
        if ($outputTokens < 0) {
            throw new \InvalidArgumentException('Output tokens cannot be negative');
        }
        if ($cost < 0.0) {
            throw new \InvalidArgumentException('Cost cannot be negative');
        }
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}