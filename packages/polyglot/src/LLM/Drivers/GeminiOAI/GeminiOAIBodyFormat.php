<?php

namespace Cognesy\Polyglot\LLM\Drivers\GeminiOAI;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class GeminiOAIBodyFormat extends OpenAICompatibleBodyFormat
{
    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        $request['response_format'] = $responseFormat ?: $request['response_format'] ?? [];

        switch($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object'
                ];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
            case OutputMode::Unrestricted:
                $request['response_format'] = $request['response_format'] ? ['type' => 'json_object'] : [];
                break;
        }

        $request['tools'] = $tools ? $this->removeDisallowedEntries($tools) : [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        return array_filter($request, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}

// Add support for:
// "reasoning_effort": "low", "medium", "high", "none"