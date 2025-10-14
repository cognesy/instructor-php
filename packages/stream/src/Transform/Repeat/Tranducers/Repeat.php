<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Repeat\Tranducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Repeat\Decorators\RepeatReducer;

final readonly class Repeat implements Transducer
{
    public function __construct(private int $times) {
        if ($times <= 0) {
            throw new \InvalidArgumentException('times must be greater than 0');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new RepeatReducer(
            inner: $reducer,
            times: $this->times,
        );
    }
}
