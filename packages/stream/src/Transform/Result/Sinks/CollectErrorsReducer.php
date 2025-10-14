<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Collects all error messages from failed Results.
 *
 * @example
 * $errors = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('e1'), Result::failure('e2')])
 *     ->withSink(new CollectErrorsReducer())
 *     ->execute();
 * // ['e1', 'e2']
 */
final readonly class CollectErrorsReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return [];
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if ($reducible instanceof Result && $reducible->isFailure()) {
            $accumulator[] = $reducible->error();
        }
        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $accumulator;
    }
}
