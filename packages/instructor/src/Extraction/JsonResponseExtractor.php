<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Extraction;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

/**
 * Extracts JSON data from LLM responses into canonical array form.
 *
 * Handles various response formats:
 * - Clean JSON content
 * - Markdown-wrapped JSON (```json ... ```)
 * - JSON embedded in text (bracket matching)
 * - Tool calls (in Tools mode)
 *
 * This formalizes the extraction stage of the response pipeline,
 * producing a canonical array that can then be deserialized.
 */
class JsonResponseExtractor implements CanExtractResponse
{
    #[\Override]
    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        $json = $response->findJsonData($mode);

        if ($json->isEmpty()) {
            /** @var Result<array<string, mixed>, string> */
            return Result::failure('No JSON found in response');
        }

        /** @var Result<array<string, mixed>, string> */
        return Result::try(fn() => $json->toArray());
    }
}
