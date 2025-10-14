<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Combine\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Combine\Decorators\InterleaveReducer;

final class Interleave implements Transducer
{
    private readonly array $iterables;

    public function __construct(iterable ...$iterables) {
        $this->iterables = $iterables;
    }

    #[\Override]
        public function __invoke(Reducer $reducer): Reducer {
        return new InterleaveReducer(
            $reducer,
            ...$this->iterables,
        );
    }
}
