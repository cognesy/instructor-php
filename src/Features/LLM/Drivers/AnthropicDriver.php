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

class AnthropicDriver implements CanHandleInference
{
    private bool $parallelToolCalls = false;

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
        return array_filter([
            'x-api-key' => $this->config->apiKey,
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
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
        $this->parallelToolCalls = $options['parallel_tool_calls'] ?? false;
        unset($options['parallel_tool_calls']);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'system' => Messages::fromArray($messages)
                ->forRoles(['system'])
                ->toString(),
            'messages' => $this->toNativeMessages(
                Messages::fromArray($messages)
                    ->exceptRoles(['system'])
                    //->toMergedPerRole()
                    ->toArray()
            ),
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_choice'] = $this->toToolChoice($toolChoice, $tools);
        }

        return $request;
    }

    // RESPONSE /////////////////////////////////////////////

    public function toLLMResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['stop_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function toPartialLLMResponse(array $data) : ?PartialLLMResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            toolId: $data['content_block']['id'] ?? '',
            toolName: $data['content_block']['name'] ?? '',
            toolArgs: $data['delta']['partial_json'] ?? '',
            finishReason: $data['delta']['stop_reason'] ?? $data['message']['stop_reason'] ?? '',
            usage: $this->makeUsage($data),
            responseData: $data,
        );
    }

    public function getStreamData(string $data): string|bool {
        if (!str_starts_with($data, 'data:')) {
            return '';
        }
        $data = trim(substr($data, 5));
        return match(true) {
            $data === 'event: message_stop' => false,
            default => $data,
        };
    }

    // PRIVATE //////////////////////////////////////////////

    private function toTools(array $tools) : array {
        $result = [];
        foreach ($tools as $tool) {
            $result[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'],
            ];
        }
        return $result;
    }

    private function toToolChoice(string|array $toolChoice, array $tools) : array {
        return match(true) {
            empty($tools) => [],
            empty($toolChoice) => [
                'type' => 'auto',
                'disable_parallel_tool_use' => !$this->parallelToolCalls,
            ],
            is_array($toolChoice) => [
                'type' => 'tool',
                'name' => $toolChoice['function']['name'],
                'disable_parallel_tool_use' => !$this->parallelToolCalls,
            ],
            default => [
                'type' => $this->mapToolChoice($toolChoice),
                'disable_parallel_tool_use' => !$this->parallelToolCalls,
            ],
        };
    }

    protected function mapToolChoice(string $choice) : string {
        return match($choice) {
            'auto' => 'auto',
            'required' => 'any',
            default => 'auto',
        };
    }

    private function toNativeMessages(array $messages) : array {
        $list = [];
        foreach ($messages as $message) {
            $nativeMessage = $this->mapMessage($message);
            if (empty($nativeMessage)) {
                continue;
            }
            $list[] = $nativeMessage;
        }
        return $list;
    }

    private function mapMessage(array $message) : array {
        return match(true) {
            ($message['role'] ?? '') === 'assistant' && !empty($message['_metadata']['tool_calls'] ?? []) => $this->toNativeToolCall($message),
            ($message['role'] ?? '') === 'tool' => $this->toNativeToolResult($message),
            default => $this->toNativeTextMessage($message),
        };
    }

    private function toNativeTextMessage(array $message) : array {
        return [
            'role' => $this->mapRole($message['role'] ?? 'user'),
            'content' => $this->toNativeContent($message['content']),
        ];
    }

    private function mapRole(string $role) : string {
        $roles = ['user' => 'user', 'assistant' => 'assistant', 'system' => 'user', 'tool' => 'user'];
        return $roles[$role] ?? $role;
    }

    private function toNativeContent(string|array $content) : string|array {
        if (is_string($content)) {
            return $content;
        }
        // if content is array - process each part
        $transformed = [];
        foreach ($content as $contentPart) {
            $transformed[] = $this->contentPartToNative($contentPart);
        }
        return $transformed;
    }

    private function contentPartToNative(array $contentPart) : array {
        $type = $contentPart['type'] ?? 'text';
        return match($type) {
            'text' => $this->toNativeTextContent($contentPart),
            'image_url' => $this->toNativeImage($contentPart),
            default => $contentPart,
        };
    }

    private function toNativeTextContent(array $contentPart) : array {
        return [
            'type' => 'text',
            'text' => $contentPart['text'] ?? '',
        ];
    }

    private function toNativeImage(array $contentPart) : array {
        $mimeType = Str::between($contentPart['image_url']['url'], 'data:', ';base64,');
        $base64content = Str::after($contentPart['image_url']['url'], ';base64,');
        $contentPart = [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $mimeType,
                'data' => $base64content,
            ],
        ];
        return $contentPart;
    }

    private function toNativeToolCall(array $message) : array {
        return [
            'role' => 'assistant',
            'content' => [[
                'type' => 'tool_use',
                'id' => $message['_metadata']['tool_calls'][0]['id'] ?? '',
                'name' => $message['_metadata']['tool_calls'][0]['function']['name'] ?? '',
                'input' => Json::from($message['_metadata']['tool_calls'][0]['function']['arguments'] ?? '')->toArray(),
            ]]
        ];
    }

    private function toNativeToolResult(array $message) : array {
        return [
            'role' => 'user',
            'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => $message['_metadata']['tool_call_id'] ?? '',
                'content' => $message['content'] ?? '',
                //'is_error' => false,
            ]]
        ];
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(array_map(
            callback: fn(array $call) => $call,
            array: array_filter(
                array: $data['content'] ?? [],
                callback: fn($part) => 'tool_use' === ($part['type'] ?? ''))
        ), fn($call) => ToolCall::fromArray([
            'id' => $call['id'] ?? '',
            'name' => $call['name'] ?? '',
            'arguments' => $call['input'] ?? ''
        ]));
    }

    private function makeContent(array $data) : string {
        return $data['content'][0]['text'] ?? Json::encode($data['content'][0]['input']) ?? '';
    }

    private function makeContentDelta(array $data) : string {
        return $data['delta']['text'] ?? $data['delta']['partial_json'] ?? '';
    }

    private function setCacheMarker(array $messages): array {
        $lastIndex = count($messages) - 1;
        $lastMessage = $messages[$lastIndex];

        if (is_array($lastMessage['content'])) {
            $subIndex = count($lastMessage['content']) - 1;
            $lastMessage['content'][$subIndex]['cache_control'] = ["type" => "ephemeral"];
        } else {
            $lastMessage['content'] = [[
                'type' => $lastMessage['type'] ?? 'text',
                'text' => $lastMessage['content'] ?? '',
                'cache_control' => ["type" => "ephemeral"],
            ]];
        }

        $messages[$lastIndex] = $lastMessage;
        return $messages;
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usage']['input_tokens']
                ?? $data['message']['usage']['input_tokens']
                ?? 0,
            outputTokens: $data['usage']['output_tokens']
                ?? $data['message']['usage']['output_tokens']
                ?? 0,
            cacheWriteTokens: $data['usage']['cache_creation_input_tokens']
                ?? $data['message']['usage']['cache_creation_input_tokens']
                ?? 0,
            cacheReadTokens: $data['usage']['cache_read_input_tokens']
                ?? $data['message']['usage']['cache_read_input_tokens']
                ?? 0,
            reasoningTokens: 0,
        );
    }
}
