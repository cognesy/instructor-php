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
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Features\LLM\InferenceRequest;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Str;

class CohereV1Driver implements CanHandleInference
{
    public function __construct(
        protected LLMConfig $config,
        protected ?CanHandleHttp $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->httpClient = $httpClient ?? HttpClient::make(events: $this->events);
    }

    // REQUEST //////////////////////////////////////////////

    public function handle(InferenceRequest $request) : CanAccessResponse {
        $request = $request->withCacheApplied();
        return $this->httpClient->handle(
            url: $this->getEndpointUrl($request),
            headers: $this->getRequestHeaders(),
            body: $this->getRequestBody(
                $request->messages(),
                $request->model(),
                $request->tools(),
                $request->toolChoice(),
                $request->responseFormat(),
                $request->options(),
                $request->mode(),
            ),
            streaming: $request->options()['stream'] ?? false,
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
        unset($options['parallel_tool_calls']);

        $system = '';
        $chatHistory = [];

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'preamble' => $system,
            'chat_history' => $chatHistory,
            'message' => Messages::asString($messages),
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->toTools($tools);
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // RESPONSE /////////////////////////////////////////////

    public function toLLMResponse(array $data): LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            //: $this->map($data),
            finishReason: $data['finish_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function toPartialLLMResponse(array $data) : PartialLLMResponse {
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $this->makeToolId($data),
            toolName: $this->makeToolNameDelta($data),
            toolArgs: $this->makeToolArgsDelta($data),
            finishReason: $data['response']['finish_reason'] ?? $data['delta']['finish_reason'] ?? '',
            usage: $this->makeUsage($data),
            responseData: $data,
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

    private function makeContent(array $data) : string {
        return ($data['text'] ?? '') . (!empty($data['tool_calls'])
            ? ("\n" . Json::encode($data['tool_calls']))
            : ''
        );
    }

    private function makeContentDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_call_delta']['parameters'] ?? $data['text'] ?? '';
    }

    private function makeToolId(array $data) {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['id'] ?? '';
    }

    private function makeToolNameDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        return $data['tool_calls'][0]['name'] ?? '';
    }

    private function makeToolArgsDelta(array $data) : string {
        if (!$this->isStreamChunk($data)) {
            return '';
        }
        $toolArgs = $data['tool_calls'][0]['parameters'] ?? '';
        return ('' === $toolArgs) ? '' : Json::encode($toolArgs);
    }

    private function isStreamChunk(array $data) : bool {
        return in_array(($data['event_type'] ?? ''), ['text-generation', 'tool-calls-chunk']);
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['meta']['tokens']['input_tokens']
                ?? $data['response']['meta']['tokens']['input_tokens']
                ?? $data['delta']['tokens']['input_tokens']
                ?? 0,
            outputTokens: $data['meta']['tokens']['output_tokens']
                ?? $data['response']['meta']['tokens']['output_tokens']
                ?? $data['delta']['tokens']['input_tokens']
                ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }

    protected function excludeUnderscoredKeys(array $messages) : array {
        $list = [];
        foreach ($messages as $message) {
            $list[] = array_filter($message, fn($value, $key) => !Str::startsWith($key, '_'), ARRAY_FILTER_USE_BOTH);
        }
        return $list;
    }
}
