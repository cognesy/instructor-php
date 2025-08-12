<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;

readonly final class Terminal implements CanProcessState
{
    public static function make(): self {
        return new self();
    }

    public static function callable(): callable {
        return function(CanCarryState $state, ?callable $next = null): CanCarryState {
            return $state;
        };
    }

    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        return $state;
    }
}