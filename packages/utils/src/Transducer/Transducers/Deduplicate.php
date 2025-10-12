<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\DeduplicateReducer;

final readonly class Deduplicate implements Transducer
{
    /**
     * @param Closure(mixed): (string|int)|null $keyFn
     */
    public function __construct(private ?Closure $keyFn = null) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DeduplicateReducer($reducer, $this->keyFn);
    }
}
