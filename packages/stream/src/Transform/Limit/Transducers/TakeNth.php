<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Limit\Decorators\TakeNthReducer;

final readonly class TakeNth implements Transducer
{
    public function __construct(private int $nth) {
        if ($nth <= 0) {
            throw new \InvalidArgumentException('nth must be greater than 0');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new TakeNthReducer(
            inner: $reducer,
            nth: $this->nth,
        );
    }
}
