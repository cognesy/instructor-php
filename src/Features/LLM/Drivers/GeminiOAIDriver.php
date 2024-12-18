<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;

class GeminiOAIDriver extends OpenAICompatibleDriver
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
