<?php

namespace Cognesy\Polyglot\LLM\Drivers\Meta;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class MetaBodyFormat extends OpenAICompatibleBodyFormat
{
    // OVERRIDES - HELPERS ///////////////////////////////////

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
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $responseFormat['json_schema']['name'] ?? $responseFormat['name'] ?? 'schema',
                        'schema' => $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [],
                        'strict' => $responseFormat['json_schema']['strict'] ?? $responseFormat['strict'] ?? true,
                    ],
                ];
                break;
        }

        $request['tools'] = $tools ?? [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        $request['tools'] = $this->removeDisallowedEntries($request['tools']);
        $request['response_format'] = $this->removeDisallowedEntries($request['response_format']);

        return array_filter($request, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}