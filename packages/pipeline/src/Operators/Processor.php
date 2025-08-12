<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;

readonly final class Processor implements CanProcessState {
    public function __construct(
        private CanProcessState $processor,
    ) {}

    public static function with(CanProcessState $processor): self {
        return new self($processor);
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $processedState = $this->processor->process($state);
        if ($processedState->isFailure()) {
            return $processedState;
        }
        return $next ? $next($processedState) : $processedState;
    }
}
