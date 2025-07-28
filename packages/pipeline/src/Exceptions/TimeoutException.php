<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Exceptions;

use Throwable;

/**
 * Exception thrown when pipeline processing times out.
 */
class TimeoutException extends PipelineException
{
    public function __construct(
        private readonly float $timeoutSeconds,
        private readonly float $elapsedSeconds,
        string $processorName = '',
        ?Throwable $previous = null,
    ) {
        $message = $processorName
            ? "Processor '{$processorName}' timed out after {$elapsedSeconds}s (limit: {$timeoutSeconds}s)"
            : "Pipeline timed out after {$elapsedSeconds}s (limit: {$timeoutSeconds}s)";

        parent::__construct(
            message: $message,
            previous: $previous,
            context: [
                'timeout_seconds' => $timeoutSeconds,
                'elapsed_seconds' => $elapsedSeconds,
            ],
            processorName: $processorName,
        );
    }

    /**
     * Get the configured timeout in seconds.
     */
    public function getTimeoutSeconds(): float {
        return $this->timeoutSeconds;
    }

    /**
     * Get the elapsed time in seconds.
     */
    public function getElapsedSeconds(): float {
        return $this->elapsedSeconds;
    }

    /**
     * Check if the timeout was exceeded by a significant margin.
     */
    public function isSignificantOverrun(float $marginPercent = 10.0): bool {
        $marginSeconds = $this->timeoutSeconds * ($marginPercent / 100);
        return $this->elapsedSeconds > ($this->timeoutSeconds + $marginSeconds);
    }
}