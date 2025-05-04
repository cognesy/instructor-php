<?php

namespace Cognesy\Polyglot\LLM\Drivers\Minimaxi;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

class MinimaxiBodyFormat extends OpenAICompatibleBodyFormat
{
    public function map(
        array        $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = '',
        array        $responseFormat = [],
        array        $options = [],
        OutputMode   $mode = OutputMode::Text,
    ) : array {
        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // OVERRIDES - HELPERS ///////////////////////////////////

    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        switch($mode) {
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = [];
                break;
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $responseFormat['json_schema']['name'] ?? $responseFormat['name'] ?? 'schema',
                        'schema' => $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [],
                    ],
                ];
                break;
            case OutputMode::Unrestricted:
                $request['response_format'] = $responseFormat ?? $request['response_format'] ?? [];
                break;
        }

        $request['tools'] = $tools ? $this->toNativeTools($tools) : [];
        $request['tool_choice'] = [];

        return array_filter($request);
    }

    protected function toNativeTools(array $tools) : array|string {
        return array_map(fn($tool) => $this->toNativeTool($tool), $tools);
    }

    protected function toNativeTool(array $tool) : array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
                'parameters' => Json::encode($tool['function']['parameters']),
            ],
        ];
    }
}