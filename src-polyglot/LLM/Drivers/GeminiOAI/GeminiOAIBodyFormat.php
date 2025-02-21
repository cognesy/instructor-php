<?php

namespace Cognesy\Polyglot\LLM\Drivers\GeminiOAI;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\Mode;

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