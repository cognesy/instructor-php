<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Sync;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

class ResponseNormalizer
{
    /**
     * Normalizes response content based on output mode.
     * Extracts JSON from content and replaces original content with it.
     */
    public function normalizeContent(InferenceResponse $response, OutputMode $mode): InferenceResponse {
        return $response->withContent(match ($mode) {
            OutputMode::Text => $response->content(),
            OutputMode::Tools => $response->toolCalls()->first()?->argsAsJson()
                ?: $response->content() // fallback if no tool calls - some LLMs return just a string
                ?: '',
            // for OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema try extracting JSON from content
            // and replacing original content with it
            default => Json::fromString($response->content())->toString(),
        });
    }
}
