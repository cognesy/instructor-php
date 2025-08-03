<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\PipelineMiddlewareInterface;
use Cognesy\Pipeline\ProcessingState;
use RuntimeException;

/**
 * Middleware that fails the pipeline when a condition is met.
 *
 * This is useful for validation and early failure scenarios where
 * you want to stop processing based on the current state.
 */
readonly class FailWhen implements PipelineMiddlewareInterface
{
    /**
     * @param Closure(ProcessingState):bool $condition
     */
    public function __construct(
        private Closure $condition,
        private string $message,
    ) {}

    /**
     * @param callable(ProcessingState):bool $condition
     */
    public static function with(callable $condition, string $message = 'Condition failed'): self {
        return new self($condition, $message);
    }

    public function handle(ProcessingState $state, callable $next): ProcessingState {
        if (($this->condition)($state)) {
            return $this->makeFailure($state);
        }
        return $next($state);
    }

    private function makeFailure(ProcessingState $state): ProcessingState {
        $e = new RuntimeException($this->message);
        return $state->failWith($e);
    }
}