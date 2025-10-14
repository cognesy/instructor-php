<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Deduplicate\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Deduplicate\Decorators\CollapseRepetitionsReducer;

final readonly class CollapseRepetitions implements Transducer
{
    /**
     * @param Closure(mixed): (string|int)|null $keyFn
     */
    public function __construct(private ?Closure $keyFn = null) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CollapseRepetitionsReducer(
            inner: $reducer,
            keyFn: $this->keyFn
        );
    }
}
