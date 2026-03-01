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
        if (!is_array($accumulator)) {
            $accumulator = ['success' => true, 'values' => [], 'error' => null];
        }
        if (!array_key_exists('success', $accumulator) || !is_bool($accumulator['success'])) {
            $accumulator['success'] = true;
        }
        if (!isset($accumulator['values']) || !is_array($accumulator['values'])) {
            $accumulator['values'] = [];
        }
        if (!array_key_exists('error', $accumulator)) {
            $accumulator['error'] = null;
        }

        if (!($reducible instanceof Result)) {
            return $accumulator;
        }

        if ($reducible->isFailure() && $accumulator['success']) {
            $accumulator['success'] = false;
            $accumulator['error'] = $reducible->error();
        } elseif ($reducible->isSuccess()) {
            $accumulator['values'][] = $reducible->unwrap();
        }

        return $accumulator;
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        if (!is_array($accumulator)) {
            return Result::failure(null);
        }
        if (!array_key_exists('success', $accumulator) || !is_bool($accumulator['success'])) {
            return Result::failure($accumulator['error'] ?? null);
        }
        if (!isset($accumulator['values']) || !is_array($accumulator['values'])) {
            $accumulator['values'] = [];
        }

        if ($accumulator['success']) {
            return Result::success($accumulator['values']);
        }
        return Result::failure($accumulator['error']);
    }
}
