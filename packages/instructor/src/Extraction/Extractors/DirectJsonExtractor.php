<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use JsonException;

/**
 * Attempts to parse content directly as JSON.
 *
 * This is the simplest extractor - it assumes the entire content
 * is valid JSON and attempts to parse it as-is.
 *
 * Best for: Clean API responses, structured output modes
 */
class DirectJsonExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        $trimmed = trim($input->content);

        if ($trimmed === '') {
            throw new ExtractionException('Empty content');
        }

        try {
            $decoded = json_decode($trimmed, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ExtractionException("Not valid JSON: {$e->getMessage()}", $e);
        }

        if (!is_array($decoded)) {
            throw new ExtractionException('JSON must decode to object or array');
        }

        return $decoded;
    }

    #[\Override]
    public function name(): string
    {
        return 'direct';
    }
}
