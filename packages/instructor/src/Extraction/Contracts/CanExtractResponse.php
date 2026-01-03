<?php


declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

/**
 * Contract for extracting structured data from LLM responses.
 *
 * Implementations handle the "mess" of LLM output (text, markdown, tool calls)
 * and produce a canonical array representation for further processing.
 */
interface CanExtractResponse
{
    /**
     * Extract structured data from an inference response.
     *
     * @param InferenceResponse $response The raw LLM response
     * @param OutputMode $mode The output mode used for the request
     * @return Result<array<string, mixed>, string> Success with extracted array or Failure
     */
    public function extract(InferenceResponse $response, OutputMode $mode): Result;
}
