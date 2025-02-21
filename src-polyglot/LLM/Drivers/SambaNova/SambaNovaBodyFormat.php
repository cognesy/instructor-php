<?php

namespace Cognesy\Polyglot\LLM\Drivers\SambaNova;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\Mode;

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