<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\DistinctReducer;

final readonly class Distinct implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DistinctReducer(reducer: $reducer);
    }
}