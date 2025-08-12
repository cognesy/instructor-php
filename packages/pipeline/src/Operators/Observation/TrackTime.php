<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators\Observation;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Tag\Observation\TimingTag;

/**
 * Pure timing middleware - captures essential timing data only.
 * 
 * Fast, lightweight, no memory tracking, no complex logic.
 * Dedicated consumer components handle SLA monitoring, timeouts, etc.
 */
readonly class TrackTime implements CanProcessState
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

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $startTime = microtime(true);

        $output = $next ? $next($state) : $state;

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $timingTag = new TimingTag(
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            operationName: $this->operationName,
            success: $output->result()->isSuccess(),
        );

        return $output->addTags($timingTag);
    }
}