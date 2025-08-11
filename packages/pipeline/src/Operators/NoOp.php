<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;

readonly final class NoOp implements CanProcessState
{
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        return $next ? $next($state) : $state;
    }
}
