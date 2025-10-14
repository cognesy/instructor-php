<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Misc\Decorators;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Throwable;

final readonly class TryCatchReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        /** @var Closure(mixed): mixed $tryFn */
        private Closure $tryFn,
        /** @var ?Closure(\Throwable, mixed): mixed $onError */
        private ?Closure $onError = null,
        private bool $throwOnError = false,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        try {
            $result = ($this->tryFn)($reducible);
            return $this->inner->step($accumulator, $result);
        } catch (Throwable $e) {
            return $this->handleError($e, $accumulator, $reducible);
        }
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    // INTERNAL ///////////////////////////////////////////////

    private function handleError(Throwable $e, mixed $accumulator, mixed $reducible) : mixed {
        return match(true) {
            $this->throwOnError => throw $e,
            ($this->onError === null) => $this->tryFallback($e, $accumulator, $reducible) ?? $accumulator,
            default => $accumulator,
        };
    }

    private function tryFallback(Throwable $e, mixed $accumulator, mixed $reducible): mixed {
        $fallback = ($this->onError)($e, $reducible);
        return match(true) {
            ($fallback !== null) => $this->inner->step($accumulator, $fallback),
            default => null,
        };
    }
}