<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\GeminiOAI;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
class GeminiOAIBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    #[\Override]
    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        // Gemini OAI does not support non-text responses for tools
        return false;
    }

    // INTERNAL /////////////////////////////////////////////

    // Gemini's OpenAI-compatible surface supports json_object and text but no schema, so
    // schema mode degrades to plain JSON.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat) : array {
        return $this->toJsonObjectResponseFormat($responseFormat);
    }

    #[\Override]
    protected function toToolChoice(InferenceRequest $request) : array|string {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();

        $result = match(true) {
            $tools->isEmpty() => '',
            $toolChoice->isEmpty() => 'auto',
            $toolChoice->isSpecific() => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice->functionName() ?? '',
                ]
            ],
            default => $toolChoice->mode(),
        };

        if (!$this->supportsToolSelection($request)) {
            $result = is_array($result) ? 'auto' : $result;
        }

        return $result;
    }
}

// Add support for:
// "reasoning_effort": "low", "medium", "high", "none"
// "extra_body": {"google": {"cached_content": {...}}}
