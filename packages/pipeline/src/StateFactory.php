<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\SkipProcessingTag;
use Cognesy\Pipeline\Tag\TagMap;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

/**
 * Creates ProcessingState instances from various inputs.
 */
final class StateFactory
{
    /**
     * Execute a callback with a raw value, handling exceptions and output conversion.
     *
     * @param callable(mixed):mixed $callback
     */
    public static function executeWithValue(
        callable $callback,
        mixed $input,
        ?ProcessingState $prior = null,
        NullStrategy $onNull = NullStrategy::Allow,
    ): ProcessingState {
        $value = self::extractValue($input);
        $existingTags = self::extractTags(match(true) {
            $input instanceof ProcessingState => $input,
            default => $prior,
        });

        try {
            $output = $callback($value);
        } catch (Throwable $exception) {
            return self::createFailureState($exception, $existingTags);
        }

        return self::makeProcessingState($output, $existingTags, $onNull);
    }

    /**
     * Execute a callback with a Result object, handling exceptions and output conversion.
     *
     * @param callable(Result):mixed $callback
     */
    public static function executeWithResult(
        callable $callback,
        Result $input,
        ?ProcessingState $prior = null,
        NullStrategy $onNull = NullStrategy::Allow,
    ): ProcessingState {
        $existingTags = self::extractTags($prior);
        try {
            $output = $callback($input);
        } catch (Throwable $exception) {
            return self::createFailureState($exception, $existingTags);
        }

        return self::makeProcessingState($output, $existingTags, $onNull);
    }

    /**
     * Execute a callback with a ProcessingState, handling exceptions and output conversion.
     *
     * @param callable(ProcessingState):mixed $callback
     */
    public static function executeWithState(
        callable $callback,
        ProcessingState $input,
        NullStrategy $onNull = NullStrategy::Allow,
    ): ProcessingState {
        $existingTags = self::extractTags($input);
        try {
            $output = $callback($input);
        } catch (Throwable $exception) {
            return $input->withResult(self::convertToFailure($exception));
        }

        return self::makeProcessingState($output, $existingTags, $onNull);
    }

    public static function fromException(
        Throwable $e,
        ?ProcessingState $prior = null,
    ) : ProcessingState{
        return self::createFailureState($e, $prior ? $prior->allTags() : []);
    }

    /**
     * Convert any input type to ProcessingState, optionally combining with prior state.
     */
    public static function fromInput(
        mixed $input,
        ?ProcessingState $prior = null,
        NullStrategy $onNull = NullStrategy::Allow,
    ): ProcessingState {
        return match (true) {
            ($input === null) => self::handleNullInput($prior ?? ProcessingState::empty(), $onNull),
            $input instanceof ProcessingState => $prior ? $input->mergeTags($prior) : $input,
            $input instanceof Result => $prior ? $prior->withResult($input) : ProcessingState::with($input, []),
            $input instanceof Throwable => self::createFailureState($input, $prior ? $prior->allTags() : []),
            default => $prior ? $prior->withResult(Result::success($input)) : ProcessingState::with($input),
        };
    }

    /**
     * Convert any value to Result with null handling.
     */
    public static function toResult(
        mixed $value,
        NullStrategy $onNull = NullStrategy::Allow,
    ): Result {
        return match (true) {
            $value instanceof Result => $value,
            $value === null && NullStrategy::Fail->is($onNull) => Result::failure(new RuntimeException('Value cannot be null')),
            $value === null && NullStrategy::Allow->is($onNull) => Result::success(null),
            default => Result::success($value),
        };
    }

    // PRIVATE METHODS /////////////////////////////////////////////////////////////////////

    private static function extractValue(mixed $input): mixed {
        return match (true) {
            $input instanceof ProcessingState => $input->value(),
            $input instanceof Result => $input->unwrap(),
            default => $input,
        };
    }

    private static function extractTags(mixed $input): array {
        return match (true) {
            $input instanceof ProcessingState => $input->allTags(),
            default => [],
        };
    }

    private static function createFailureState(Throwable $exception, array $existingTags): ProcessingState {
        $failure = self::convertToFailure($exception);
        $errorTag = self::convertToErrorTag($exception);
        $tags = TagMap::create($existingTags)->with($errorTag);

        return new ProcessingState($failure, $tags);
    }

    private static function makeProcessingState(mixed $output, array $existingTags, NullStrategy $onNull): ProcessingState {
        return match (true) {
            $output === null && $onNull === NullStrategy::Allow => new ProcessingState(
                Result::success(null),
                TagMap::create($existingTags),
            ),
            $output === null && $onNull === NullStrategy::Fail => self::createFailureState(
                new RuntimeException('Callback returned null, but NullStrategy is set to Fail'),
                $existingTags
            ),
            $output === null && $onNull === NullStrategy::Skip => new ProcessingState(
                Result::success(null),
                TagMap::create($existingTags)->with(new SkipProcessingTag()),
            ),
            $output instanceof ProcessingState => $output,
            $output instanceof Result => new ProcessingState($output, TagMap::create($existingTags)),
            default => new ProcessingState(Result::success($output), TagMap::create($existingTags)),
        };
    }

    private static function handleNullInput(ProcessingState $state, NullStrategy $onNull): ProcessingState {
        return match ($onNull) {
            NullStrategy::Skip => $state,
            NullStrategy::Fail => self::createFailureState(
                new RuntimeException('Input value is null'),
                $state->allTags(),
            ),
            NullStrategy::Allow => $state->withResult(Result::success(null)),
        };
    }

    private static function convertToFailure(mixed $error): Failure {
        return match (true) {
            $error instanceof Result => $error,
            $error instanceof Throwable => Result::failure($error),
            is_string($error) => Result::failure(new RuntimeException($error)),
            default => Result::failure(new RuntimeException(json_encode(['error' => $error]))),
        };
    }

    private static function convertToErrorTag(mixed $error): ErrorTag {
        return match (true) {
            $error instanceof Throwable => ErrorTag::fromException($error),
            $error instanceof Result => ErrorTag::fromResult($error),
            default => ErrorTag::fromMessage((string)$error),
        };
    }
}