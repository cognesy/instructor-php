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
            case OutputMode::Text:
            case OutputMode::MdJson:
                unset($request['response_format']);
                break;
            case OutputMode::Unrestricted:
                if (!empty($request['response_format'])) {
                    $request['response_format'] = [
                        'type' => 'json_object',
                    ];
                }
                break;
        }

        $request['tools'] = $tools ? $this->removeDisallowedEntries($tools) : [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        return array_filter($request);
    }
}