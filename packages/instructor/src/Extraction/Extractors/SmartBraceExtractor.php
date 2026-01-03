<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Utils\Result\Result;
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
class SmartBraceExtractor implements CanExtractContent
{
    #[\Override]
    public function extract(string $content): Result
    {
        $length = strlen($content);
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $content[$i];

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
                    $json = substr($content, $start, $i - $start + 1);

                    try {
                        json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
                        return Result::success($json);
                    } catch (JsonException) {
                        // Continue searching for another valid object
                        $start = null;
                    }
                }
            }
        }

        return Result::failure('No valid JSON found with smart brace matching');
    }

    #[\Override]
    public function name(): string
    {
        return 'smart_brace_matching';
    }
}
