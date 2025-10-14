<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Decorators;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Wraps a reducer to only process failed Results, extracting error messages.
 * Successes are skipped without affecting the accumulator.
 *
 * @example
 * $errors = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e1'), Result::failure('e2')])
 *     ->withSink(new OnFailureReducer(new ToArrayReducer()))
 *     ->execute();
 * // ['e1', 'e2']
 */
final readonly class OnFailureReducer implements Reducer
{
    public function __construct(
        private Reducer $inner
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isFailure()) {
            return $this->inner->step($accumulator, $reducible->error());
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
