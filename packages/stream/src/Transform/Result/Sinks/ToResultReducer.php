<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Result\Sinks;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;

/**
 * Aggregates Results: Success if all succeed with array of values,
 * otherwise Failure with first error.
 *
 * @example
 * $result = Transformation::define()
 *     ->withInput([Result::success(1), Result::success(2), Result::success(3)])
 *     ->withSink(new ToResultReducer())
 *     ->execute();
 * // Result::success([1, 2, 3])
 *
 * $result = Transformation::define()
 *     ->withInput([Result::success(1), Result::failure('error'), Result::success(3)])
 *     ->withSink(new ToResultReducer())
 *     ->execute();
 * // Result::failure('error')
 */
final readonly class ToResultReducer implements Reducer
{
    #[\Override]
    public function init(): mixed {
        return ['success' => true, 'values' => [], 'error' => null];
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (!($reducible instanceof Result)) {
            return $accumulator;
        }

        if ($reducible->isFailure() && is_bool($accumulator['success']) && $accumulator['success']) {
            $accumulator['success'] = false;
            $accumulator['error'] = $reducible->error();
        } elseif ($reducible->isSuccess()) {
            $accumulator['values'][] = $reducible->unwrap();
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        if ($accumulator['success']) {
            return Result::success($accumulator['values']);
        }
        return Result::failure($accumulator['error']);
    }
}
