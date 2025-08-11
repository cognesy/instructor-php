<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators\Observation;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\Observation\StepTimingTag;

/**
 * Pure step timing hook - captures step-level timing data only.
 * 
 * Fast, lightweight, no logic - just data collection.
 * Consumer components handle step SLA monitoring, performance analysis, etc.
 */
readonly class StepTiming implements CanProcessState
{
    public function __construct(
        private string $stepName,
    ) {}

    /**
     * Create step timing hook to capture timing data for a specific step.
     */
    public static function capture(string $stepName): self {
        return new self($stepName);
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $startTime = microtime(true);

        $output = $next ? $next($state) : $state;

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $stepTiming = new StepTimingTag(
            stepName: $this->stepName,
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            success: $output->result()->isSuccess(),
        );

        return $output->withTags($stepTiming);
    }
}