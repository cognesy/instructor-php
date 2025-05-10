<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV1;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Messages\Messages;

class CohereV1BodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        OutputMode $mode
    ): array {
        $options = array_merge($this->config->options, $options);

        unset($options['parallel_tool_calls']);

        $system = '';
        $chatHistory = [];
        $nativeMessages = Messages::asString($this->messageFormat->map($messages));

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'preamble' => $system,
            'chat_history' => $chatHistory,
            'message' => $nativeMessages,
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->toTools($tools);
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // INTERNAL /////////////////////////////////////////////

    private function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        switch($mode) {
            case OutputMode::Json:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['schema'] ?? [],
                ];
                break;
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['json_schema']['schema'] ?? [],
                ];
                break;
        }
        return $request;
    }

    private function toTools(array $tools): array {
        $result = [];
        foreach ($tools as $tool) {
            $parameters = [];
            foreach ($tool['function']['parameters']['properties'] as $name => $param) {
                $parameters[$name] = array_filter([
                    'description' => $param['description'] ?? '',
                    'type' => $this->toCohereType($param),
                    'required' => in_array(
                        needle: $name,
                        haystack: $tool['function']['parameters']['required'] ?? [],
                    ),
                ]);
            }
            $result[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameterDefinitions' => $parameters,
            ];
        }
        return $result;
    }

    private function toCohereType(array $param) : string {
        return match($param['type']) {
            'string' => 'str',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array' => throw new \Exception('Array type not supported by Cohere'),
            'object' => throw new \Exception('Object type not supported by Cohere'),
            default => throw new \Exception('Unknown type'),
        };
    }
}