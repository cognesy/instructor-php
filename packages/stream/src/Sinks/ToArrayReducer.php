<?php declare(strict_types=1);

namespace Cognesy\Stream\Sinks;

use Cognesy\Stream\Contracts\Reducer;

final class ToArrayReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return [];
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $accumulator[] = $reducible;
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
