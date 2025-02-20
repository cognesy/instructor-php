<?php

namespace Cognesy\LLM\LLM\Drivers\SambaNova;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\LLM\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;

class SambaNovaBodyFormat extends OpenAICompatibleBodyFormat
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
            case Mode::JsonSchema:
                $request['response_format'] = [ "type" => "json_object" ];
                break;
        }
        return $request;
    }
}