<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\InterleaveReducer;

final class Interleave implements Transducer
{
    private readonly array $iterables;

    public function __construct(iterable ...$iterables) {
        $this->iterables = $iterables;
    }

    #[\Override]
        public function __invoke(Reducer $reducer): Reducer {
        return new InterleaveReducer($reducer, ...$this->iterables);
    }
}
