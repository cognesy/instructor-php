<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\ProcessingState;

/**
 * Convenience processor for callables that expect ProcessingState objects.
 * 
 * Use when you need access to the full processing state, tags, or result
 * rather than just the unwrapped value.
 */
class StateProcessor implements ProcessorInterface
{
    private function __construct(
        private readonly Closure $callable,
    ) {}

    public static function from(callable $callable): self {
        return new self($callable);
    }

    public function process(ProcessingState $state): ProcessingState {
        return ($this->callable)($state);
    }
}