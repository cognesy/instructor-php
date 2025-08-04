<?php

namespace Cognesy\Pipeline\Middleware;

use Closure;
use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

/**
 * Middleware that skips processing based on a condition.
 *
 * This middleware allows you to skip further processing in the pipeline
 * if a certain condition is met, returning the current state without modification.
 */
readonly class SkipProcessing implements CanControlStateProcessing {
    /**
     * @param Closure(ProcessingState):bool $condition
     */
    public function __construct(
        private Closure $condition,
    ) {}

    /**
     * @param callable(ProcessingState):bool $condition
     */
    public static function with(callable $condition): self {
        return new self($condition);
    }

    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $shouldSkip = ($this->condition)($state);
        return match(true) {
            $shouldSkip => $state,
            default => $next($state),
        };
    }
}