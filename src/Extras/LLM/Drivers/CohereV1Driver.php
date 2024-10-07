<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\Http\Contracts\CanHandleResponse;
use Cognesy\Instructor\Extras\Http\HttpClient;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Extras\LLM\Data\PartialLLMResponse;
use Cognesy\Instructor\Extras\LLM\Data\ToolCall;
use Cognesy\Instructor\Extras\LLM\Data\ToolCalls;
use Cognesy\Instructor\Extras\LLM\InferenceRequest;
use Cognesy\Instructor\Utils\Json\Json;

class CohereV1Driver implements CanHandleInference
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

    public function handle(InferenceRequest $request) : CanHandleResponse {
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

    public function getEndpointUrl(InferenceRequest $request) : string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    public function getRequestHeaders() : array {
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];
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
        $system = '';
        $chatHistory = [];

        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'preamble' => $system,
            'chat_history' => $chatHistory,
            'message' => Messages::asString($messages),
        ], $options));

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // RESPONSE /////////////////////////////////////////////

    public function toLLMResponse(array $data): LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            responseData: $data,
            toolsData: $this->mapToolsData($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            inputTokens: $data['meta']['tokens']['input_tokens'] ?? 0,
            outputTokens: $data['meta']['tokens']['output_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function toPartialLLMResponse(array $data) : PartialLLMResponse {
        return new PartialLLMResponse(
            delta: $this->makeDelta($data),
            responseData: $data,
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['response']['finish_reason'] ?? $data['delta']['finish_reason'] ?? '',
            inputTokens: $data['response']['meta']['tokens']['input_tokens'] ?? $data['delta']['tokens']['input_tokens'] ?? 0,
            outputTokens: $data['response']['meta']['tokens']['output_tokens'] ?? $data['delta']['tokens']['input_tokens'] ?? 0,
            cacheCreationTokens: 0,
            cacheReadTokens: 0,
        );
    }

    public function getData(string $data): string|bool {
        $data = trim($data);
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
                $request['tools'] = $this->toTools($tools);
                break;
            case Mode::Json:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['schema'] ?? [],
                ];
                break;
            case Mode::JsonSchema:
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

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(
            $data['tool_calls'] ?? [],
            fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['parameters'] ?? ''])
        );
    }

    private function mapToolsData(array $data) : array {
        return array_map(
            fn($tool) => [
                'name' => $tool['name'] ?? '',
                'arguments' => $tool['parameters'] ?? '',
            ],
            $data['tool_calls'] ?? []
        );
    }

    private function makeContent(array $data) : string {
        return ($data['text'] ?? '') . (!empty($data['tool_calls'])
            ? ("\n" . Json::encode($data['tool_calls']))
            : ''
        );
    }

    private function makeDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_call_delta']['parameters'] ?? $data['text'] ?? '';
    }

    private function makeToolArgsDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        $toolArgs = $data['tool_calls'][0]['parameters'] ?? '';
        return ('' === $toolArgs) ? '' : Json::encode($toolArgs);
    }

    private function makeToolNameDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['name'] ?? '';
    }

    private function isStreamChunk(array $data) : bool {
        return in_array(($data['event_type'] ?? ''), ['text-generation', 'tool-calls-chunk']);
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
}
