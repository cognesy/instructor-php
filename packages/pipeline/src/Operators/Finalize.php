<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

class Finalize implements CanProcessState {
    public function __construct(
        private CanProcessState $finalizer,
    ) {}

    public static function with(callable|CanProcessState $finalizer): self {
        return new self(match(true) {
            $finalizer instanceof CanProcessState => $finalizer,
            default => Call::withState($finalizer),
        });
    }

    #[\Override]
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        $finalizedState = $this->finalizer->process($state);
        if ($finalizedState->isFailure()) {
            return $finalizedState;
        }
        return $next ? $next($finalizedState) : $finalizedState;
    }
}