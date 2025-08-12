<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators\Observation;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\Observation\MemoryTag;

/**
 * Pure memory tracking middleware - captures memory usage data only.
 * 
 * Separate from timing for clean concerns and optional use.
 * Consumer components handle leak detection, capacity planning, etc.
 */
readonly class TrackMemory implements CanProcessState
{
    public function __construct(
        private ?string $operationName = null,
    ) {}

    /**
     * Create memory middleware to capture memory usage data.
     */
    public static function capture(?string $operationName = null): self {
        return new self($operationName);
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);

        $output = $next ? $next($state) : $state;

        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);

        $memoryTag = new MemoryTag(
            startMemory: $startMemory,
            endMemory: $endMemory,
            memoryUsed: $endMemory - $startMemory,
            startPeakMemory: $startPeakMemory,
            endPeakMemory: $endPeakMemory,
            peakMemoryUsed: $endPeakMemory - $startPeakMemory,
            operationName: $this->operationName,
        );

        return $output->addTags($memoryTag);
    }
}