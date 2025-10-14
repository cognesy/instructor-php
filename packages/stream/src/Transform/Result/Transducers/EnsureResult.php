<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Validates Result values with predicates, converting to failure if check fails.
 *
 * @example
 * new EnsureResult(
 *     predicate: fn($x) => $x > 0,
 *     errorMessage: fn($x) => "Value $x must be positive"
 * )
 */
final readonly class EnsureResult implements Transducer
{
    /**
     * @param Closure(mixed): bool $conditionFn
     * @param Closure(mixed): string|string $errorMessage
     */
    public function __construct(
        private Closure $conditionFn,
        private Closure|string $errorMessage,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                $result = $reducible instanceof Result
                    ? $reducible
                    : Result::from($reducible);

                $validated = $result->ensure(
                    $this->conditionFn,
                    is_string($this->errorMessage)
                        ? fn() => $this->errorMessage
                        : $this->errorMessage
                );
                return $reducer->step($accumulator, $validated);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
