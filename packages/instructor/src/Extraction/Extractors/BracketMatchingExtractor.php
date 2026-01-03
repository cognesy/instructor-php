<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Utils\Result\Result;
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
class BracketMatchingExtractor implements CanExtractContent
{
    #[\Override]
    public function extract(string $content): Result
    {
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');

        if ($firstBrace === false) {
            return Result::failure('No opening brace found');
        }

        if ($lastBrace === false) {
            return Result::failure('No closing brace found');
        }

        if ($lastBrace <= $firstBrace) {
            return Result::failure('Invalid brace positions');
        }

        $json = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);

        try {
            json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (JsonException $e) {
            return Result::failure("Invalid JSON between braces: {$e->getMessage()}");
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'bracket_matching';
    }
}
