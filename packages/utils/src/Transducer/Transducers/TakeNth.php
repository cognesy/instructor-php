<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\TakeNthReducer;

final readonly class TakeNth implements Transducer
{
    public function __construct(private int $nth) {
        if ($nth <= 0) {
            throw new \InvalidArgumentException('nth must be greater than 0');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new TakeNthReducer($this->nth, $reducer);
    }
}
