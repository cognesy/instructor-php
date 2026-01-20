<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Instructor\Extraction\Data\ExtractionInput;

/**
 * Contract for extracting structured data from LLM responses.
 *
 * Implementations handle the "mess" of LLM output (text, markdown, tool calls)
 * and produce a canonical array representation for further processing.
 */
interface CanExtractResponse
{
    /**
     * Extract structured data from prepared input.
     *
     * @return array<array-key, mixed>
     * @throws \Throwable
     */
    public function extract(ExtractionInput $input): array;

    /**
     * Extractor name for debugging and logging.
     */
    public function name(): string;
}
