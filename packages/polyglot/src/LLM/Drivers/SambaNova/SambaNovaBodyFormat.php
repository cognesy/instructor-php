<?php

namespace Cognesy\Polyglot\LLM\Drivers\SambaNova;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class SambaNovaBodyFormat extends OpenAICompatibleBodyFormat
{
    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        switch($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                ];
                break;
//            case OutputMode::Text:
//            case OutputMode::MdJson:
//                $request['response_format'] = ['type' => 'text'];
//                break;
            case OutputMode::Unrestricted:
                $request['response_format'] = $request['response_format'] ?? $responseFormat ?? [];
                break;
        }

        $request['tools'] = $tools ? $this->removeDisallowedEntries($tools) : [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        return array_filter($request);
    }

//    protected function applyMode(
//        array        $request,
//        OutputMode   $mode,
//        array        $tools,
//        string|array $toolChoice,
//        array        $responseFormat
//    ) : array {
//        switch($mode) {
//            case OutputMode::Json:
//            case OutputMode::JsonSchema:
//                $request['response_format'] = [ "type" => "json_object" ];
//                break;
//        }
//        return $request;
//    }
}