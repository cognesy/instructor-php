<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use JsonException;

/**
 * Extracts JSON with smart brace matching that handles string escaping.
 *
 * Unlike simple bracket matching, this extractor:
 * - Tracks brace depth properly
 * - Ignores braces inside quoted strings
 * - Handles escaped quotes within strings
 *
 * Best for: Complex JSON with nested objects and strings containing braces
 */
class SmartBraceExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        $length = strlen($input->content);
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $input->content[$i];

            // Handle escape sequences
            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            // Toggle string state on unescaped quotes
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            // Skip brace counting while inside strings
            if ($inString) {
                continue;
            }

            // Track brace depth
            if ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                // Found complete JSON object
                if ($depth === 0 && $start !== null) {
                    $json = substr($input->content, $start, $i - $start + 1);

                    try {
                        $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        // Continue searching for another valid object
                        $start = null;
                        continue;
                    }

                    if (!is_array($decoded)) {
                        $start = null;
                        continue;
                    }

                    return $decoded;
                }
            }
        }

        throw new ExtractionException('No valid JSON found with smart brace matching');
    }

    #[\Override]
    public function name(): string
    {
        return 'smart_brace_matching';
    }
}
