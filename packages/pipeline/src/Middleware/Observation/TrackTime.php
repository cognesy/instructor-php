<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware\Observation;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\Observation\TimingTag;

/**
 * Pure timing middleware - captures essential timing data only.
 * 
 * Fast, lightweight, no memory tracking, no complex logic.
 * Dedicated consumer components handle SLA monitoring, timeouts, etc.
 */
readonly class TrackTime implements CanControlStateProcessing
{
    public function __construct(
        private ?string $operationName = null,
    ) {}

    /**
     * Create timing middleware to capture timing data.
     */
    public static function capture(?string $operationName = null): self {
        return new self($operationName);
    }

    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $startTime = microtime(true);

        $output = $next($state);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $timingTag = new TimingTag(
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            operationName: $this->operationName,
            success: $output->result()->isSuccess(),
        );

        return $output->withTags($timingTag);
    }
}