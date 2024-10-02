<?php

namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Data\ApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\PartialApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\ToolCalls;
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json\Json;

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
        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $messages,
        ], $options));

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

    public function toApiResponse(array $data): ApiResponse {
        return new ApiResponse(
            content: $this->makeContent($data),
            responseData: $data,
            toolsData: $this->makeToolsData($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            inputTokens: $data['meta']['billed_units']['input_tokens'] ?? 0,
            outputTokens: $data['meta']['billed_units']['output_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function toPartialApiResponse(array $data) : ?PartialApiResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialApiResponse(
            delta: $this->makeDelta($data),
            responseData: $data,
            toolName: $data['delta']['message']['tool_calls']['function']['name'] ?? '',
            toolArgs: $data['delta']['message']['tool_calls']['function']['arguments'] ?? '',
            finishReason: $data['delta']['finish_reason'] ?? '',
            inputTokens: $data['delta']['usage']['billed_units']['input_tokens'] ?? 0,
            outputTokens: $data['delta']['usage']['billed_units']['output_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function getData(string $data): string|bool {
        if (!str_starts_with($data, 'data:')) {
            return '';
        }
        $data = trim(substr($data, 5));
        return match(true) {
            $data === '"[DONE]"' => false,
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
            case Mode::Tools:
                $request['tools'] = $this->removeDisallowedEntries($tools);
                break;
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
            'title',
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
            callback: fn(array $call) => $call['function'] ?? [],
            array: $data['message']['tool_calls'] ?? []
        ));
    }

    private function makeToolsData(array $data) : array {
        return array_map(
            fn($tool) => [
                'name' => $tool['function']['name'] ?? '',
                'arguments' => Json::parse($tool['function']['arguments']) ?? '',
            ],
            $data['message']['tool_calls'] ?? []
        );
    }

    private function makeDelta(array $data): string {
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
}
