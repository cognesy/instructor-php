<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\Stamps\TimingStamp;

/**
 * Middleware that measures execution time for pipeline processors.
 * 
 * Adds TimingStamp to envelopes with precise timing information including:
 * - Start time (high precision)
 * - End time (high precision) 
 * - Duration in seconds (float)
 * - Optional operation name for identification
 * 
 * Multiple timing stamps can be accumulated to track timing across
 * different pipeline stages and processors.
 * 
 * Example usage:
 * ```php
 * $pipeline = Pipeline::for($data)
 *     ->withMiddleware(new TimingMiddleware('data_processing'))
 *     ->through(fn($x) => expensiveOperation($x))
 *     ->process();
 * 
 * $timing = $pipeline->envelope()->last(TimingStamp::class);
 * echo "Processing took: " . ($timing->duration * 1000) . "ms";
 * ```
 */
readonly class TimingMiddleware implements PipelineMiddlewareInterface
{
    public function __construct(
        private ?string $operationName = null,
        private int $precision = 6, // Microsecond precision by default
    ) {}

    public function handle(Envelope $envelope, callable $next): Envelope
    {
        $startTime = microtime(true);
        
        // Execute the next middleware/processor (never throws - returns envelope)
        $result = $next($envelope);
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, $this->precision);
        
        // Inspect envelope state to determine success/failure
        $success = $result->result()->isSuccess();
        $error = null;
        
        // Extract error details from ErrorStamp if failure occurred
        if (!$success) {
            $errorStamp = $result->first(\Cognesy\Pipeline\Stamps\ErrorStamp::class);
            $error = $errorStamp?->getMessage() ?? 'Unknown error';
        }
        
        // Create timing stamp based on envelope inspection
        $timingStamp = new TimingStamp(
            startTime: $startTime,
            endTime: $endTime,
            duration: $duration,
            operationName: $this->operationName,
            success: $success,
            error: $error
        );
        
        return $result->with($timingStamp);
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