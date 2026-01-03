<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Utils\Result\Result;
use JsonException;

/**
 * Extracts JSON from markdown code blocks.
 *
 * Handles formats like:
 * - ```json ... ```
 * - ``` ... ```
 * - ```JSON ... ```
 *
 * Best for: LLM responses that wrap JSON in markdown formatting
 */
class MarkdownBlockExtractor implements CanExtractContent
{
    #[\Override]
    public function extract(string $content): Result
    {
        $trimmed = trim($content);

        // Pattern matches: ```json ... ``` or ``` ... ```
        // Captures content between code fence markers
        $pattern = '/```(?:json|JSON)?\s*\n?(.*?)\n?\s*```/s';

        if (!preg_match($pattern, $trimmed, $matches)) {
            return Result::failure('No markdown code block found');
        }

        $json = trim($matches[1]);

        if ($json === '') {
            return Result::failure('Empty code block');
        }

        try {
            json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (JsonException $e) {
            return Result::failure("Invalid JSON in code block: {$e->getMessage()}");
        }
    }

    #[\Override]
    public function name(): string
    {
        return 'markdown_code_block';
    }
}
