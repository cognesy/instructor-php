<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\DeduplicateReducer;

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
