<?php

namespace Cognesy\Polyglot\LLM\Drivers\Minimaxi;

use Cognesy\Polyglot\LLM\Drivers\OpenAICompatible\OpenAICompatibleBodyFormat;
use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Utils\Json\Json;

class MinimaxiBodyFormat extends OpenAICompatibleBodyFormat
{
    public function map(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->toNativeTools($tools);
        }

        unset($request['tool_choice']); // currently not supported by Minimaxi
        unset($request['response_format']); // currently not supported by Minimaxi

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // OVERRIDES - HELPERS ///////////////////////////////////

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