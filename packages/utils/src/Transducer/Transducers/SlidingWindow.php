<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\SlidingWindowReducer;

final readonly class SlidingWindow implements Transducer {
    public function __construct(private int $size) {}

    #[\Override]
    public function __invoke(Reducer $reducer) : Reducer {
        return new SlidingWindowReducer($this->size, $reducer);
    }
}