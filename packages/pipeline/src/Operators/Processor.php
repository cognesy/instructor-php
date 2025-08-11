<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

readonly final class Processor implements CanProcessState {
    public function __construct(
        private CanProcessState $processor,
    ) {}

    public static function with(CanProcessState $processor): self {
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
