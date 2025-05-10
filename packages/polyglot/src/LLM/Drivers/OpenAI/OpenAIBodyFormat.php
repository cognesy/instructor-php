<?php

namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

class OpenAIBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) {}

    public function map(
        array        $messages,
        string       $model,
        array        $tools,
        array|string $toolChoice,
        array        $responseFormat,
        array        $options,
        OutputMode   $mode,
    ): array {
        $options = array_merge($this->config->options, $options);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->messageFormat->map($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        $request = $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);

        return $request;
    }

    // INTERNAL ///////////////////////////////////////////////

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
                    'type' => 'json_object'
                ];
                break;
            case OutputMode::Text:
            case OutputMode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
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
            case OutputMode::Unrestricted:
                $request['response_format'] = $responseFormat ?? $request['response_format'] ?? [];
                break;
        }

        $request['tools'] = $tools ?? [];
        $request['tool_choice'] = $toolChoice ?? [];

        return array_filter($request);
    }
}