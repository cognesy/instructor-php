<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

readonly final class NoOp implements CanProcessState
{
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        return $next ? $next($state) : $state;
    }
}
