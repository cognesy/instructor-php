<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Extras;

use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

/**
 * Pipeline-based string normalization helpers.
 *
 * This holds the {@see Pipeline}-driven implementation of the case-normalization
 * routine that previously lived in `Cognesy\Utils\Str`. It was moved here to break
 * the circular dependency between the `utils` and `pipeline` packages: `utils` is a
 * foundation package and must not depend on `pipeline`, which itself depends on `utils`.
 *
 * `Str` keeps a dependency-free equivalent for its own case helpers; use this class
 * when you specifically want the Pipeline-based transformation chain (e.g. to extend
 * it with additional stages, error strategies, or tags).
 */
final class StringNormalizer
{
    /**
     * Collapses any casing convention (camel/Pascal/snake/kebab/SCREAMING) into a
     * single space-separated string, applying each transform as a Pipeline stage.
     */
    public static function spaceSeparated(string $input): string {
        $pipeline = Pipeline::builder(ErrorStrategy::FailFast)
            ->throughAll(...self::transforms())
            ->create();

        $result = $pipeline
            ->executeWith(ProcessingState::with($input))
            ->value();

        return is_string($result) ? $result : $input;
    }

    /**
     * The ordered list of pure string transforms used to normalize casing.
     *
     * @return array<int, callable(string): string>
     */
    public static function transforms(): array {
        return [
            // separate groups of capitalized words
            fn (string $data): string => preg_replace('/([A-Z])([a-z])/', ' $1$2', $data) ?? $data,
            // separate groups of capitalized words of 2+ characters with spaces
            fn (string $data): string => preg_replace('/([A-Z]{2,})/', ' $1 ', $data) ?? $data,
            // de-kebab
            fn (string $data): string => str_replace('-', ' ', $data),
            // de-snake
            fn (string $data): string => str_replace('_', ' ', $data),
            // remove double spaces
            fn (string $data): string => preg_replace('/\s+/', ' ', $data) ?? $data,
            // remove leading _
            fn (string $data): string => ltrim($data, '_'),
            // remove leading -
            fn (string $data): string => ltrim($data, '-'),
            // trim space
            fn (string $data): string => trim($data),
        ];
    }
}
