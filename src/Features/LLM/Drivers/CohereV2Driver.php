<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Arrays;

class CohereV2Driver extends OpenAIDriver
{
    // REQUEST //////////////////////////////////////////////

    public function getRequestBody(
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

    public function getRequestHeaders(): array {
        $optional = [
            'X-Client-Name' => $this->config->metadata['client_name'] ?? '',
        ];
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $optional);
    }

    // RESPONSE //////////////////////////////////////////////

    public function toLLMResponse(array $data): LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function toPartialLLMResponse(array|null $data) : ?PartialLLMResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['delta']['message']['tool_calls']['function']['id'] ?? '',
            toolName: $data['delta']['message']['tool_calls']['function']['name'] ?? '',
            toolArgs: $data['delta']['message']['tool_calls']['function']['arguments'] ?? '',
            finishReason: $data['delta']['finish_reason'] ?? '',
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function getData(string $data): string|bool {
        if (!str_starts_with($data, 'data:')) {
            return '';
        }
        $data = trim(substr($data, 5));
        return match(true) {
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    // OVERRIDES - HELPERS ///////////////////////////////////

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

    private function makeContent(array $data): string {
        $contentMsg = $data['message']['content'][0]['text'] ?? '';
        $contentFnArgs = $data['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $this->makeToolCall($call),
            array: $data['message']['tool_calls'] ?? [],
        ));
    }

    private function makeToolCall(array $data) : ?ToolCall {
        if (empty($data)) {
            return null;
        }
        if (!isset($data['function'])) {
            return null;
        }
        if (!isset($data['id'])) {
            return null;
        }
        return ToolCall::fromArray($data['function'] ?? [])->withId($data['id'] ?? '');
    }

    private function makeContentDelta(array $data): string {
        $deltaContent = match(true) {
            ([] !== ($data['delta']['message']['content'] ?? [])) => $this->normalizeContent($data['delta']['message']['content']),
            default => '',
        };
        $deltaFnArgs = $data['delta']['message']['tool_calls']['function']['arguments'] ?? '';
        return match(true) {
            '' !== $deltaContent => $deltaContent,
            '' !== $deltaFnArgs => $deltaFnArgs,
            default => ''
        };
    }

    private function normalizeContent(array|string $content) : string {
        return is_array($content) ? $content['text'] : $content;
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usage']['billed_units']['input_tokens']
                ?? $data['delta']['usage']['billed_units']['input_tokens']
                ?? 0,
            outputTokens: $data['usage']['billed_units']['output_tokens']
                ?? $data['delta']['usage']['billed_units']['output_tokens']
                ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
