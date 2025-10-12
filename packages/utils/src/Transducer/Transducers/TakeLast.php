<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\TakeLastReducer;

final readonly class TakeLast implements Transducer
{
    public function __construct(private int $amount) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('amount must be non-negative');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new TakeLastReducer($this->amount, $reducer);
    }
}
