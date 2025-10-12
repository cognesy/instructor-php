<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\CycleReducer;

final readonly class Cycle implements Transducer
{
    public function __construct(private ?int $times = null) {
        if ($times !== null && $times <= 0) {
            throw new \InvalidArgumentException('times must be greater than 0 or null for infinite cycling');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CycleReducer($this->times, $reducer);
    }
}
