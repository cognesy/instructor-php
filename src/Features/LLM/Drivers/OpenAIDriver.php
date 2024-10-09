<?php

namespace Cognesy\Instructor\Features\LLM\Drivers;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\HttpClient;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Features\LLM\InferenceRequest;
use Cognesy\Instructor\Utils\Json\Json;

class OpenAIDriver implements CanHandleInference
{
    public function __construct(
        protected LLMConfig $config,
        protected ?CanHandleHttp $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make();
    }

    // REQUEST //////////////////////////////////////////////

    public function handle(InferenceRequest $request) : CanAccessResponse {
        $request = $this->withCachedContext($request);
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
        $extras = array_filter([
            "OpenAI-Organization" => $this->config->metadata['organization'] ?? '',
            "OpenAI-Project" => $this->config->metadata['project'] ?? '',
        ]);
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

    public function toLLMResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            responseData: $data,
            toolsData: $this->makeToolsData($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
        );
    }

    public function toPartialLLMResponse(array|null $data) : ?PartialLLMResponse {
        if ($data === null || empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            responseData: $data,
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['choices'][0]['finish_reason'] ?? '',
            usage: $this->makeUsage($data),
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

    private function withCachedContext(InferenceRequest $request): InferenceRequest {
        if (!isset($request->cachedContext)) {
            return $request;
        }

        $cloned = clone $request;
        $cloned->messages = array_merge($request->cachedContext->messages, $request->messages);
        $cloned->tools = empty($request->tools) ? $request->cachedContext->tools : $request->tools;
        $cloned->toolChoice = empty($request->toolChoice) ? $request->cachedContext->toolChoice : $request->toolChoice;
        $cloned->responseFormat = empty($request->responseFormat) ? $request->cachedContext->responseFormat : $request->responseFormat;
        return $cloned;
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
                'arguments' => Json::decode($tool['function']['arguments']) ?? '',
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

    private function makeContentDelta(array $data): string {
        $deltaContent = $data['choices'][0]['delta']['content'] ?? '';
        $deltaFnArgs = $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
        return match(true) {
            ('' !== $deltaContent) => $deltaContent,
            ('' !== $deltaFnArgs) => $deltaFnArgs,
            default => ''
        };
    }

    private function makeToolNameDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['name'] ?? '';
    }

    private function makeToolArgsDelta(array $data) : string {
        return $data['choices'][0]['delta']['tool_calls'][0]['function']['arguments'] ?? '';
    }

    private function makeUsage(array $data): Usage {
        return new Usage(
            inputTokens: $data['usage']['prompt_tokens']
                ?? $data['x_groq']['usage']['prompt_tokens']
                ?? 0,
            outputTokens: $data['usage']['completion_tokens']
                ?? $data['x_groq']['usage']['completion_tokens']
                ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: $data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0,
            reasoningTokens: 0,
        );
    }
}
