<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Utils\Result\Result;

/**
 * Contract for content extraction strategies.
 *
 * Each extractor implements a specific approach to extracting structured content
 * from raw response data (direct parsing, markdown blocks, bracket matching, etc.).
 *
 * This interface is format-agnostic. Extractors find and isolate structured content
 * that could be JSON, XML, YAML, PHP arrays, etc. Format-specific parsing is handled
 * downstream by the deserializer.
 *
 * Extractors are composable - ResponseExtractor uses a chain of extractors
 * with fallback behavior, trying each until one succeeds.
 */
interface CanExtractContent
{
    /**
     * Attempt to extract structured content from the given raw content.
     *
     * @param string $content Raw content that may contain structured data
     * @return Result<string, string> Success with extracted content string, or Failure with reason
     */
    public function extract(string $content): Result;

    /**
     * Extractor name for debugging and logging.
     */
    public function name(): string;
}
