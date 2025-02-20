<?php

namespace Cognesy\LLM\LLM\Drivers\SambaNova;

use Cognesy\LLM\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\LLM\LLM\Enums\Mode;

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