<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

readonly final class Processor implements CanControlStateProcessing {
    public function __construct(
        private CanControlStateProcessing $processor,
    ) {}

    public static function with(CanControlStateProcessing $processor): self {
        return new self($processor);
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $processedState = $this->processor->process($state);
        if ($processedState->isFailure()) {
            return $processedState;
        }
        return $next ? $next($processedState) : $processedState;
    }
}
