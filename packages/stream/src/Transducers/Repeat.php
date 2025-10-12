<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\RepeatReducer;

final readonly class Repeat implements Transducer
{
    public function __construct(private int $times) {
        if ($times <= 0) {
            throw new \InvalidArgumentException('times must be greater than 0');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new RepeatReducer($this->times, $reducer);
    }
}
