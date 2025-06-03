<?php

namespace Cognesy\Polyglot\LLM\Drivers\GeminiOAI;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\InferenceRequest;

class GeminiOAIBodyFormat extends OpenAICompatibleBodyFormat
{
    // CAPABILITIES /////////////////////////////////////////

    protected function supportsNonTextResponseForTools(InferenceRequest $request) : bool {
        // Gemini OAI does not support non-text responses for tools
        return false;
    }

    // INTERNAL /////////////////////////////////////////////

    protected function toResponseFormat(InferenceRequest $request) : array {
        $mode = $this->toResponseFormatMode($request);
        switch ($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $result = ['type' => 'json_object'];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $result = ['type' => 'text'];
                break;
            default:
                $result = [];
        }
        return $result;
    }

    protected function toToolChoice(InferenceRequest $request) : array|string {
        $tools = $request->tools();
        $toolChoice = $request->toolChoice();

        $result = match(true) {
            empty($tools) => '',
            empty($toolChoice) => 'auto',
            is_array($toolChoice) => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice['function']['name'] ?? '',
                ]
            ],
            default => $toolChoice,
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
