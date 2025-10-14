<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Limit\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Limit\Decorators\DropFirstReducer;

final readonly class DropFirst implements Transducer
{
    public function __construct(private int $amount) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('amount must be non-negative');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DropFirstReducer(
            inner: $reducer,
            amount: $this->amount,
        );
    }
}