<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\ScanReducer;

final readonly class Scan implements Transducer
{
    /**
     * @param Closure(mixed, mixed): mixed $scanFn
     */
    public function __construct(
        private Closure $scanFn,
        private mixed $init,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ScanReducer($reducer, $this->scanFn, $this->init);
    }
}
