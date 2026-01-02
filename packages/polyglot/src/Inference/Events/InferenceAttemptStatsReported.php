<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Cognesy\Polyglot\Inference\Data\InferenceAttemptStats;
use Psr\Log\LogLevel;

/**
 * Dispatched when an inference attempt completes with statistics.
 *
 * Contains metrics for a single attempt including:
 * - Duration and TTFC (for streaming)
 * - Token usage breakdown
 * - Throughput (tokens/second)
 * - Success/failure status and error details
 */
final class InferenceAttemptStatsReported extends InferenceEvent
{
    public function __construct(
        public readonly InferenceAttemptStats $stats,
    ) {
        parent::__construct($stats->toArray());
        $this->logLevel = $stats->isSuccess ? LogLevel::INFO : LogLevel::WARNING;
    }

    #[\Override]
    public function __toString(): string {
        return (string) $this->stats;
    }
}
