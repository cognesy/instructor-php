<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators\Observation;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\Observation\StepMemoryTag;

/**
 * Pure step memory hook - captures step-level memory usage data only.
 * 
 * Separate from timing for clean concerns and optional use.
 * Consumer components handle memory leak detection, optimization, etc.
 */
readonly class StepMemory implements CanControlStateProcessing
{
    public function __construct(
        private string $stepName,
    ) {}

    /**
     * Create step memory hook to capture memory data for a specific step.
     */
    public static function capture(string $stepName): self {
        return new self($stepName);
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $startMemory = memory_get_usage(true);

        $output = $next ? $next($state) : $state;

        $endMemory = memory_get_usage(true);

        $stepMemory = new StepMemoryTag(
            stepName: $this->stepName,
            startMemory: $startMemory,
            endMemory: $endMemory,
            memoryUsed: $endMemory - $startMemory,
        );

        return $output->withTags($stepMemory);
    }
}