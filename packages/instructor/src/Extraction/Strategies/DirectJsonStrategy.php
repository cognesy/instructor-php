<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Utils\Result\Result;
use JsonException;

/**
 * Attempts to parse content directly as JSON.
 *
 * This is the simplest strategy - it assumes the entire content
 * is valid JSON and attempts to parse it as-is.
 *
 * Best for: Clean API responses, structured output modes
 */
class DirectJsonStrategy implements ExtractionStrategy
{
    #[\Override]
    public function extract(string $content): Result
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return Result::failure('Empty content');
        }

        try {
            json_decode($trimmed, associative: true, flags: JSON_THROW_ON_ERROR);
            return Result::success($trimmed);
        } catch (JsonException $e) {
            return Result::failure("Not valid JSON: {$e->getMessage()}");
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'direct';
    }
}
