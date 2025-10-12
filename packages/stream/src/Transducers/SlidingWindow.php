<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\SlidingWindowReducer;

final readonly class SlidingWindow implements Transducer {
    public function __construct(private int $size) {}

    #[\Override]
    public function __invoke(Reducer $reducer) : Reducer {
        return new SlidingWindowReducer($this->size, $reducer);
    }
}