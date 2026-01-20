<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use JsonException;

/**
 * Extracts JSON by finding first '{' and last '}'.
 *
 * Simple bracket matching that finds the outermost JSON object
 * by locating the first opening brace and last closing brace.
 *
 * Best for: Text with embedded JSON where content wraps the JSON
 * Limitation: Doesn't handle escaped braces inside strings
 */
class BracketMatchingExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        $firstBrace = strpos($input->content, '{');
        $lastBrace = strrpos($input->content, '}');

        if ($firstBrace === false) {
            throw new ExtractionException('No opening brace found');
        }

        if ($lastBrace === false) {
            throw new ExtractionException('No closing brace found');
        }

        if ($lastBrace <= $firstBrace) {
            throw new ExtractionException('Invalid brace positions');
        }

        $json = substr($input->content, $firstBrace, $lastBrace - $firstBrace + 1);

        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ExtractionException("Invalid JSON between braces: {$e->getMessage()}", $e);
        }

        if (!is_array($decoded)) {
            throw new ExtractionException('Bracket-matched JSON must decode to object or array');
        }

        return $decoded;
    }

    #[\Override]
    public function name(): string
    {
        return 'bracket_matching';
    }
}
