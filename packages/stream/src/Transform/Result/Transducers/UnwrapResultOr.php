<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Unwraps Results, using default value for failures.
 *
 * @example
 * new UnwrapResultOr(defaultValue: 0)
 * // [Result::success(1), Result::failure('err'), Result::success(2)]
 * // → [1, 0, 2]
 */
final readonly class UnwrapResultOr implements Transducer
{
    /**
     * @param mixed|Closure(\Throwable): mixed $defaultValue
     */
    public function __construct(private mixed $defaultValue) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    $value = is_callable($this->defaultValue)
                        ? $reducible->valueOr(fn() => ($this->defaultValue)($reducible->exceptionOr(null)))
                        : $reducible->valueOr($this->defaultValue);
                    return $reducer->step($accumulator, $value);
                }
                return $reducer->step($accumulator, $reducible);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
