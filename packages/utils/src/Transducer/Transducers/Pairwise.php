<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;

final readonly class Pairwise implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return (new SlidingWindow(2))($reducer);
    }
}
