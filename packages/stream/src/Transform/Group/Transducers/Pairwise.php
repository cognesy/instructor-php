<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Group\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

final readonly class Pairwise implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return (new SlidingWindow(2))($reducer);
    }
}
