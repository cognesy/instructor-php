<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Enums\Mode;
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
use Cognesy\Instructor\Utils\Str;

class AnthropicDriver implements CanHandleInference
{
    public function __construct(
        protected LLMConfig $config,
        protected ?CanHandleHttp $httpClient = null,
    ) {
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
        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'system' => Messages::fromArray($messages)
                ->forRoles(['system'])
                ->toString(),
            'messages' => $this->toNativeMessages(Messages::fromArray($messages)
                ->exceptRoles(['system'])
                ->toMergedPerRole()
                ->toArray()
            ),
        ], $options));

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    // RESPONSE /////////////////////////////////////////////

    public function toLLMResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            responseData: $data,
//            toolName: $data['content'][0]['name'] ?? '',
//            toolArgs: Json::encode($data['content'][0]['input'] ?? ''),
            toolsData: $this->mapToolsData($data),
            finishReason: $data['stop_reason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            inputTokens: $data['usage']['input_tokens'] ?? 0,
            outputTokens: $data['usage']['output_tokens'] ?? 0,
            cacheCreationTokens: $data['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadTokens: $data['usage']['cache_read_input_tokens'] ?? 0,
        );
    }

    public function toPartialLLMResponse(array $data) : ?PartialLLMResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            delta: $this->makeDelta($data),
            responseData: $data,
            toolName: $data['content_block']['name'] ?? '',
            toolArgs: $data['delta']['partial_json'] ?? '',
            finishReason: $data['delta']['stop_reason'] ?? $data['message']['stop_reason'] ?? '',
            inputTokens: $data['message']['usage']['input_tokens'] ?? $data['usage']['input_tokens'] ?? 0,
            outputTokens: $data['message']['usage']['output_tokens'] ?? $data['usage']['output_tokens'] ?? 0,
            cacheCreationTokens: $data['message']['usage']['cache_creation_input_tokens'] ?? $data['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadTokens: $data['message']['usage']['cache_read_input_tokens'] ?? $data['usage']['cache_read_input_tokens'] ?? 0,
        );
    }

    public function getData(string $data): string|bool {
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

    private function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        if ($mode->is(Mode::Tools)) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_choice'] = $this->toToolChoice($toolChoice, $tools);
        }
        return $request;
    }

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

    private function toToolChoice(string|array $toolChoice, array $tools) : array|string {
        return match(true) {
            empty($tools) => '',
            is_array($toolChoice) => [
                'type' => 'tool',
                'name' => $toolChoice['function']['name'],
            ],
            empty($toolChoice) => [
                'type' => 'auto',
            ],
            default => [
                'type' => $toolChoice,
            ],
        };
    }

    private function toNativeMessages(array $messages) : array {
        return array_map(
            fn($message) => [
                'role' => $this->mapRole($message['role'] ?? 'user'),
                'content' => $this->toNativeContent($message['content']),
            ],
            $messages
        );
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
        if ($type === 'image_url') {
            $contentPart = $this->toNativeImage($contentPart);
        }
        return $contentPart;
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
    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(array_map(
            callback: fn(array $call) => $call,
            array: $data['content'] ?? []
        ), fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['input'] ?? '']));
    }

    private function mapToolsData(array $data) : array {
        return array_map(
            fn($tool) => [
                'name' => $tool['name'] ?? '',
                'arguments' => $tool['input'] ?? '',
            ],
            array_filter($data['content'] ?? [], fn($part) => 'tool_use' === ($part['type'] ?? ''))
        );
    }

    private function makeContent(array $data) : string {
        return $data['content'][0]['text'] ?? Json::encode($data['content'][0]['input']) ?? '';
    }

    private function makeDelta(array $data) : string {
        return $data['delta']['text'] ?? $data['delta']['partial_json'] ?? '';
    }

    private function withCachedContext(InferenceRequest $request): InferenceRequest {
        if (!isset($request->cachedContext)) {
            return $request;
        }

        $cloned = clone $request;

        $cloned->messages = empty($request->cachedContext->messages)
            ? $request->messages
            : array_merge($this->setCacheMarker($request->cachedContext->messages), $request->messages);
        $cloned->tools = empty($request->tools) ? $request->cachedContext->tools : $request->tools;
        $cloned->toolChoice = empty($request->toolChoice) ? $request->cachedContext->toolChoice : $request->toolChoice;
        $cloned->responseFormat = empty($request->responseFormat) ? $request->cachedContext->responseFormat : $request->responseFormat;
        return $cloned;
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
}
