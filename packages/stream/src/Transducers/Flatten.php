<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\FlattenReducer;

final readonly class Flatten implements Transducer
{
    public function __construct(private int $depth = 1) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new FlattenReducer($this->depth, $reducer);
    }
}
