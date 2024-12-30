<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\CohereV2;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Instructor\Utils\Arrays;

class CohereV2RequestAdapter extends OpenAIRequestAdapter
{
    public function toHeaders(): array {
        $optional = [
            'X-Client-Name' => $this->config->metadata['client_name'] ?? '',
        ];
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $optional);
    }

    public function toRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        unset($options['parallel_tool_calls']);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->toNativeMessages($messages),
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->removeDisallowedEntries($tools);
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // INTERNAL //////////////////////////////////////////////

    protected function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Json:
                $request['response_format'] = $responseFormat;
                break;
            case Mode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['json_schema']['schema'],
                ];
                break;
        }
        return $request;
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'x-title',
            'x-php-class',
            'additionalProperties',
        ]);
    }
}