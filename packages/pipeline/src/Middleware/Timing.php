<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TimingTag;

/**
 * Middleware that measures execution time for pipeline processors.
 *
 * Adds TimingTag to states with precise timing information including:
 * - Start time (high precision)
 * - End time (high precision)
 * - Duration in seconds (float)
 * - Optional operation name for identification
 *
 * Multiple timing tags can be accumulated to track timing across
 * different pipeline stages and processors.
 */
readonly class Timing implements CanControlStateProcessing
{
    public function __construct(
        private ?string $operationName = null,
        private int $precision = 6, // Microsecond precision by default
    ) {}

    /**
     * Create a timing middleware with a specific operation name.
     */
    public static function makeNamed(string $operationName, int $precision = 6): self {
        return new self($operationName, $precision);
    }

    /**
     * Create a timing middleware with default settings.
     */
    public static function make(int $precision = 6): self {
        return new self(null, $precision);
    }

    /**
     * @param callable(ProcessingState):ProcessingState $next The next middleware/processor to execute
     */
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $startTime = microtime(true);

        // Execute the next middleware/processor (never throws - returns state)
        $output = $next($state);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, $this->precision);

        // Inspect state state to determine success/failure
        $success = $output->result()->isSuccess();
        $error = null;

        // Extract error details from ErrorTag if failure occurred
        if (!$success) {
            $errorTag = $output->firstTag(ErrorTag::class);
            $error = $errorTag?->getMessage() ?? 'Unknown error';
        }

        // Create timing tag based on state inspection
        $timingTag = new TimingTag(
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            operationName: $this->operationName,
            success: $success,
            error: $error,
        );

        return $output->withTags($timingTag);
    }
}