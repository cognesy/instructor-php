<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceExecutionStats;
use Psr\Log\LogLevel;

/**
 * Dispatched when an inference execution completes with comprehensive statistics.
 *
 * Contains aggregate metrics across all attempts including:
 * - Total duration and TTFC
 * - Cumulative token usage
 * - Throughput (tokens/second)
 * - Attempt count and success/failure breakdown
 * - Per-attempt stats breakdown
 */
final class InferenceExecutionStatsReported extends InferenceEvent
{
    public function __construct(
        public readonly InferenceExecutionStats $stats,
    ) {
        parent::__construct($stats->toArray());
        $this->logLevel = LogLevel::INFO;
    }

    #[\Override]
    public function __toString(): string {
        return (string) $this->stats;
    }
}
