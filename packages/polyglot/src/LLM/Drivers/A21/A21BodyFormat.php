<?php

namespace Cognesy\Polyglot\LLM\Drivers\A21;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class A21BodyFormat extends OpenAICompatibleBodyFormat
{
    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ): array {
        $request['response_format'] = $responseFormat ?: $request['response_format'] ?? [];

        switch ($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                ];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
        }

        $request['tools'] = $tools ?? [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        $request['tools'] = $this->removeDisallowedEntries($request['tools']);
        $request['response_format'] = $this->removeDisallowedEntries($request['response_format']);

        return array_filter($request, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}