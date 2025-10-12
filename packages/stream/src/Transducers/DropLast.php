<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\DropLastReducer;

final readonly class DropLast implements Transducer
{
    public function __construct(private int $amount) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('amount must be non-negative');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DropLastReducer($this->amount, $reducer);
    }
}
