<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Utils\Json\Partial\ResilientJson;

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
class ResilientJsonExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        $trimmed = trim($input->content);

        if ($trimmed === '') {
            throw new ExtractionException('Empty content');
        }

        try {
            $parsed = ResilientJson::parse($trimmed);

            // Only accept objects/arrays - scalars indicate parsing of non-JSON text
            if (!is_array($parsed)) {
                throw new ExtractionException('Resilient parsing produced scalar, not object/array');
            }
        } catch (\Throwable $e) {
            throw new ExtractionException("Resilient parsing failed: {$e->getMessage()}", $e);
        }

        return $parsed;
    }

    #[\Override]
    public function name(): string
    {
        return 'resilient';
    }
}
