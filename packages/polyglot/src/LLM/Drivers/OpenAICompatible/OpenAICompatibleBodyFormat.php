<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAICompatible;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Arrays;

class OpenAICompatibleBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function map(
        array        $messages = [],
        string       $model = '',
        array        $tools = [],
        string|array $toolChoice = '',
        array        $responseFormat = [],
        array        $options = [],
        OutputMode   $mode = OutputMode::Text,
    ) : array {
        $options = array_merge($this->config->options, $options);

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
            case OutputMode::Json:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'json_schema' => $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [],
                ];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [],
                ];
                break;
            case OutputMode::Unrestricted:
                $request['response_format'] = $request['response_format'] ?? $responseFormat ?? [];
                break;
        }

        $request['tools'] = $tools ? $this->removeDisallowedEntries($tools) : [];
        $request['tool_choice'] = $tools ? $this->toToolChoice($tools, $toolChoice) : [];

        return array_filter($request);
    }


    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    protected function toToolChoice(array $tools, array|string $toolChoice) : array|string {
        return match(true) {
            empty($tools) => '',
            empty($toolChoice) => 'auto',
            is_array($toolChoice) => [
                'type' => 'function',
                'function' => [
                    'name' => $toolChoice['function']['name'],
                ]
            ],
            default => $toolChoice,
        };
    }
}