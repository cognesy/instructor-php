<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\FlattenReducer;

final readonly class Flatten implements Transducer
{
    public function __construct(private int $depth = 1) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new FlattenReducer($this->depth, $reducer);
    }
}
