<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

class Finalize implements CanControlStateProcessing {
    public function __construct(
        private CanControlStateProcessing $finalizer,
    ) {}

    public static function with(callable|CanControlStateProcessing $finalizer): self {
        return new self(match(true) {
            $finalizer instanceof CanControlStateProcessing => $finalizer,
            default => Call::withState($finalizer),
        });
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        $finalizedState = $this->finalizer->process($state);
        if ($finalizedState->isFailure()) {
            return $finalizedState;
        }
        return $next ? $next($finalizedState) : $finalizedState;
    }
}