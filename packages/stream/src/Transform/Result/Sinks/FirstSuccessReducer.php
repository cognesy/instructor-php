<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;
use Cognesy\Utils\Result\Result;

/**
 * Returns first successful Result, or failure if none succeed.
 *
 * @example
 * $result = Transformation::define()
 *     ->withInput([Result::failure('e1'), Result::success(42), Result::success(99)])
 *     ->withSink(new FirstSuccessReducer())
 *     ->execute();
 * // Result::success(42)
 */
final readonly class FirstSuccessReducer implements Reducer
{
    public function __construct(
        private mixed $defaultValue = null,
    ) {}

    #[\Override]
    public function init(): mixed {
        return null;
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isSuccess()) {
            return new Reduced($reducible);
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator ?? Result::failure($this->defaultValue ?? 'No successful result found');
    }
}
