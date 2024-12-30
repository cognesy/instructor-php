<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\SambaNova;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAICompatible\OpenAICompatibleRequestAdapter;

class SambaNovaRequestAdapter extends OpenAICompatibleRequestAdapter
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