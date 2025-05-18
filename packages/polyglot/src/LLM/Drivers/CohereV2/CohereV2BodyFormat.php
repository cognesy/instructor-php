<?php

namespace Cognesy\Polyglot\LLM\Drivers\CohereV2;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Arrays;

class CohereV2BodyFormat implements CanMapRequestBody
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
        OutputMode   $mode = OutputMode::Unrestricted,
    ) : array {
        $options = array_merge($this->config->options, $options);

        unset($options['parallel_tool_calls']);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // INTERNAL //////////////////////////////////////////////

    protected function applyMode(
        array        $request,
        OutputMode   $mode,
        array        $tools,
        string|array $toolChoice,
        array        $responseFormat
    ) : array {
        $request['response_format'] = $responseFormat ?: $request['response_format'] ?? [];

        switch($mode) {
            case OutputMode::Json:
            case OutputMode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'json_schema' => $responseFormat['json_schema']['schema'] ?? $responseFormat['schema'] ?? [],
                ];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
        }

        $request['tools'] = $tools ?? [];
        unset($request['tool_choice']);

        $request['tools'] = $this->removeDisallowedEntries($request['tools']);
        $request['response_format'] = $this->removeDisallowedEntries($request['response_format']);

        return array_filter($request, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively(
            array: $jsonSchema,
            keys: [
                'x-title',
                'x-php-class',
                'additionalProperties',
            ],
        );
    }
}