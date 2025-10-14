<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Misc\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Misc\Decorators\TapReducer;

final readonly class Tap implements Transducer
{
    /**
     * @param Closure(mixed): void $sideEffectFn
     */
    public function __construct(private Closure $sideEffectFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new TapReducer(
            inner: $reducer,
            sideEffectFn: $this->sideEffectFn
        );
    }
}
