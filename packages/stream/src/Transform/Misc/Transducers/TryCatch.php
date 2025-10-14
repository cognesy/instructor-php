<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Misc\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Misc\Decorators\TryCatchReducer;

final readonly class TryCatch implements Transducer
{
    /**
     * @param Closure(mixed): mixed $tryFn
     * @param Closure(\Throwable, mixed): mixed|null $onError
     */
    public function __construct(
        private Closure $tryFn,
        private ?Closure $onError = null,
        private bool $throwOnError = true,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new TryCatchReducer(
            inner: $reducer,
            tryFn: $this->tryFn,
            onError: $this->onError,
            throwOnError: $this->throwOnError,
        );
    }
}