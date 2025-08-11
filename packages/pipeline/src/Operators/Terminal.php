<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Operators;

use Cognesy\Pipeline\Contracts\CanControlStateProcessing;
use Cognesy\Pipeline\ProcessingState;

readonly final class Terminal implements CanControlStateProcessing
{
    public static function make(): self {
        return new self();
    }

    public static function callable(): callable {
        return function(ProcessingState $state, ?callable $next = null): ProcessingState {
            return $state;
        };
    }

    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        return $state;
    }
}