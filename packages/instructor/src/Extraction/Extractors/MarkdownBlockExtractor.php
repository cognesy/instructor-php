<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Extractors;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
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
class MarkdownBlockExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        $trimmed = trim($input->content);

        // Pattern matches: ```json ... ``` or ``` ... ```
        // Captures content between code fence markers
        $pattern = '/```(?:json|JSON)?\s*\n?(.*?)\n?\s*```/s';

        if (!preg_match($pattern, $trimmed, $matches)) {
            throw new ExtractionException('No markdown code block found');
        }

        $json = trim($matches[1]);

        if ($json === '') {
            throw new ExtractionException('Empty code block');
        }

        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ExtractionException("Invalid JSON in code block: {$e->getMessage()}", $e);
        }

        if (!is_array($decoded)) {
            throw new ExtractionException('Code block JSON must decode to object or array');
        }

        return $decoded;
    }

    #[\Override]
    public function name(): string
    {
        return 'markdown_code_block';
    }
}
