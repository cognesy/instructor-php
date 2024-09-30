<?php

namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\HttpClient;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\ApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Data\PartialApiResponse;
use Cognesy\Instructor\Extras\LLM\Data\ToolCalls;
use Cognesy\Instructor\Extras\LLM\InferenceRequest;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Http\Message\ResponseInterface;

class OpenAIDriver implements CanHandleInference
{
    public function __construct(
        protected LLMConfig $config,
        protected ?CanHandleHttp $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::make();
    }

    // REQUEST //////////////////////////////////////////////

    public function handle(InferenceRequest $request) : ResponseInterface {
        return $this->httpClient->handle(
            url: $this->getEndpointUrl($request),
            headers: $this->getRequestHeaders(),
            body: $this->getRequestBody(
                $request->messages,
                $request->model,
                $request->tools,
                $request->toolChoice,
                $request->responseFormat,
                $request->options,
                $request->mode,
            ),
            streaming: $request->options['stream'] ?? false,
        );
    }

    public function getEndpointUrl(InferenceRequest $request): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    public function getRequestHeaders() : array {
        $extras = [
            "OpenAI-Organization" => $this->config->metadata['organization'] ?? '',
            "OpenAI-Project" => $this->config->metadata['project'] ?? '',
        ];
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $extras);
    }

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

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // RESPONSE /////////////////////////////////////////////

    public function toApiResponse(array $data): ?ApiResponse {
        return new ApiResponse(
            content: $this->makeContent($data),
            responseData: $data,
            toolsData: $this->makeToolsData($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            inputTokens: $this->makeInputTokens($data),
            outputTokens: $this->makeOutputTokens($data),
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
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            inputTokens: $this->makeInputTokens($data),
            outputTokens: $this->makeOutputTokens($data),
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
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    // PRIVATE //////////////////////////////////////////////

    private function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $tools;
                $request['tool_choice'] = $toolChoice;
                break;
            case Mode::Json:
                $request['response_format'] = ['type' => 'json_object'];
                break;
            case Mode::JsonSchema:
                $request['response_format'] = $responseFormat;
                break;
            case Mode::Text:
            case Mode::MdJson:
                $request['response_format'] = ['type' => 'text'];
                break;
        }
        return $request;
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromArray(array_map(
            callback: fn(array $call) => $call['function'] ?? [],
            array: $data['choices'][0]['message']['tool_calls'] ?? []
        ));
    }

    private function makeToolsData(array $data) : array {
        return array_map(
            fn($tool) => [
                'name' => $tool['function']['name'] ?? '',
                'arguments' => Json::parse($tool['function']['arguments']) ?? '',
            ],
            $data['choices'][0]['message']['tool_calls'] ?? []
        );
    }

    private function makeContent(array $data): string {
        $contentMsg = $data['choices'][0]['message']['content'] ?? '';
        $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            !empty($contentMsg) => $contentMsg,
            !empty($contentFnArgs) => $contentFnArgs,
            default => ''
        };
    }

    private function makeDelta(array $data): string {
        $deltaContent = $data['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            ('' !== $deltaContent) => $deltaContent,
            ('' !== $deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
    }

    private function makeInputTokens(array $data): int {
        return $data['usage']['prompt_tokens']
            ?? $data['x_groq']['usage']['prompt_tokens']
            ?? 0;
    }

    private function makeOutputTokens(array $data): int {
        return $data['usage']['completion_tokens']
            ?? $data['x_groq']['usage']['completion_tokens']
            ?? 0;
    }

    private function makeToolNameDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
    }

    private function makeToolArgsDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
    }
}
