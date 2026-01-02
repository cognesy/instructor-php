<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Strategies;

use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Utils\Json\Partial\ResilientJson;
use Cognesy\Utils\Result\Result;

/**
 * Attempts to parse content using resilient JSON parsing.
 *
 * Uses ResilientJsonParser which handles common JSON errors:
 * - Trailing commas: {"name":"John",}
 * - Unclosed strings: {"name":"John
 * - Unbalanced braces: {"name":"John"
 * - Unknown escape sequences
 *
 * Best for: LLM responses with minor JSON formatting issues
 */
class ResilientJsonStrategy implements ExtractionStrategy
{
    #[\Override]
    public function extract(string $content): Result
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            return Result::failure('Empty content');
        }

        try {
            $parsed = ResilientJson::parse($trimmed);

            // Only accept objects/arrays - scalars indicate parsing of non-JSON text
            if (!is_array($parsed)) {
                return Result::failure("Resilient parsing produced scalar, not object/array");
            }

            // ResilientJson::parse returns mixed, encode back to JSON string
            $json = json_encode($parsed, JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (\Throwable $e) {
            return Result::failure("Resilient parsing failed: {$e->getMessage()}");
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'resilient';
    }
}
