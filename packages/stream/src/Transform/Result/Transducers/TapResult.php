<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Transducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Support\CallableReducer;
use Cognesy\Utils\Result\Result;

/**
 * Executes side effects based on Result state without modifying values.
 *
 * @example
 * new TapResult(
 *     onSuccess: fn($val) => log("Success: $val"),
 *     onFailure: fn($err) => log("Error: $err")
 * )
 */
final readonly class TapResult implements Transducer
{
    /**
     * @param Closure(mixed): void|null $onSuccess
     * @param Closure(\Throwable): void|null $onFailure
     */
    public function __construct(
        private ?Closure $onSuccess = null,
        private ?Closure $onFailure = null,
    ) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CallableReducer(
            stepFn: function(mixed $accumulator, mixed $reducible) use ($reducer): mixed {
                if ($reducible instanceof Result) {
                    if ($this->onSuccess !== null) {
                        $reducible->ifSuccess($this->onSuccess);
                    }
                    if ($this->onFailure !== null) {
                        $reducible->ifFailure($this->onFailure);
                    }
                }
                return $reducer->step($accumulator, $reducible);
            },
            completeFn: $reducer->complete(...),
            initFn: $reducer->init(...),
        );
    }
}
