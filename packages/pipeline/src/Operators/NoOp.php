<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

readonly final class NoOp implements CanControlStateProcessing
{
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        return $next ? $next($state) : $state;
    }
}
