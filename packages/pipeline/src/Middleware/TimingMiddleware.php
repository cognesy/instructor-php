<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Tag\TimingTag;

/**
 * Middleware that measures execution time for pipeline processors.
 * 
 * Adds TimingTag to computations with precise timing information including:
 * - Start time (high precision)
 * - End time (high precision) 
 * - Duration in seconds (float)
 * - Optional operation name for identification
 * 
 * Multiple timing tags can be accumulated to track timing across
 * different pipeline stages and processors.
 * 
 * Example usage:
 * ```php
 * $pipeline = Pipeline::for($data)
 *     ->withMiddleware(new TimingMiddleware('data_processing'))
 *     ->through(fn($x) => expensiveOperation($x))
 *     ->process();
 * 
 * $timing = $pipeline->computation()->last(TimingTag::class);
 * echo "Processing took: " . ($timing->duration * 1000) . "ms";
 * ```
 */
readonly class TimingMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(
        private ?string $operationName = null,
        private int $precision = 6, // Microsecond precision by default
    ) {}

    public function handle(Computation $computation, callable $next): Computation
    {
        $startTime = microtime(true);
        
        // Execute the next middleware/processor (never throws - returns computation)
        $result = $next($computation);
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, $this->precision);
        
        // Inspect computation state to determine success/failure
        $success = $result->result()->isSuccess();
        $error = null;
        
        // Extract error details from ErrorTag if failure occurred
        if (!$success) {
            $errorTag = $result->first(\Cognesy\Pipeline\Tag\ErrorTag::class);
            $error = $errorTag?->getMessage() ?? 'Unknown error';
        }
        
        // Create timing tag based on computation inspection
        $timingTag = new TimingTag(
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            operationName: $this->operationName,
            success: $success,
            error: $error
        );
        
        return $result->with($timingTag);
    }

    /**
     * Create a timing middleware with a specific operation name.
     */
    public static function for(string $operationName, int $precision = 6): self
    {
        return new self($operationName, $precision);
    }

    /**
     * Create a timing middleware with default settings.
     */
    public static function create(int $precision = 6): self
    {
        return new self(null, $precision);
    }
}