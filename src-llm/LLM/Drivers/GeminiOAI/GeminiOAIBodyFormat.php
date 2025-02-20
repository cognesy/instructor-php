<?php

namespace Cognesy\LLM\LLM\Drivers\GeminiOAI;

use Cognesy\LLM\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\LLM\LLM\Enums\Mode;

class GeminiOAIBodyFormat extends OpenAICompatibleBodyFormat
{
    protected function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Json:
                $request['response_format'] = [ "type" => "json_object" ];
                break;
            case Mode::JsonSchema:
                $request['response_format'] = $responseFormat;
                break;
        }
        return $request;
    }
}