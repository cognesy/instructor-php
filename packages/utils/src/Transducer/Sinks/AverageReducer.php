<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Sinks;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;

final class AverageReducer implements Reducer
{
    /** @var Closure(mixed): (int|float)|null */
    private ?Closure $mapFn;
    private int $count = 0;

    /**
     * @param Closure(mixed): (int|float)|null $mapFn
     */
    public function __construct(
        ?Closure $mapFn = null
    ) {
        $this->mapFn = $mapFn;
    }

    #[\Override]
    public function init(): mixed {
        $this->count = 0;
        return 0;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $value = $this->mapFn !== null
            ? ($this->mapFn)($reducible)
            : $reducible;

        $this->count++;
        return $accumulator + $value;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        if ($this->count === 0) {
            return 0;
        }

        return $accumulator / $this->count;
    }
}
