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
use Cognesy\Instructor\Utils\Arrays;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Str;

class GeminiDriver implements CanHandleInference
{
    public function __construct(
        protected LLMConfig      $config,
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
            streaming: $request->options['stream'] ?? false,
        );
    }

    public function getEndpointUrl(InferenceRequest $request): string {
        $urlParams = ['key' => $this->config->apiKey];

        if ($request->options['stream'] ?? false) {
            $this->config->endpoint = '/models/{model}:streamGenerateContent';
            $urlParams['alt'] = 'sse';
        } else {
            $this->config->endpoint = '/models/{model}:generateContent';
        }

        return str_replace(
            search: "{model}",
            replace: $request->model ?: $this->config->model,
            subject: "{$this->config->apiUrl}{$this->config->endpoint}?" . http_build_query($urlParams));
    }

    public function getRequestHeaders() : array {
        return [
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
        $request = array_filter([
            'systemInstruction' => $this->toSystem($messages),
            'contents' => $this->toMessages($messages),
            'generationConfig' => $this->toOptions($options, $responseFormat, $mode),
        ]);

        if (!empty($tools)) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_config'] = $this->toToolChoice($toolChoice);
        }

        return $request;
    }

    // RESPONSE /////////////////////////////////////////////

    public function toLLMResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
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
            toolId: $data['candidates'][0]['id'] ?? '',
            toolName: $this->makeToolName($data),
            toolArgs: $this->makeToolArgs($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
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
            $data === '[DONE]' => false,
            default => $data,
        };
    }

    // PRIVATE //////////////////////////////////////////////

    private function toSystem(array $messages) : array {
        $system = Messages::fromArray($messages)
            ->forRoles(['system'])
            ->toString();

        return empty($system) ? [] : ['parts' => ['text' => $system]];
    }

    private function toMessages(array $messages) : array {
        return $this->toNativeMessages(
            Messages::fromArray($messages)
                ->exceptRoles(['system'])
                //->toMergedPerRole()
                ->toArray()
        );
    }

    protected function toOptions(
        array $options,
        array $responseFormat,
        Mode $mode,
    ) : array {
        return array_filter([
            "responseMimeType" => $this->toResponseMimeType($mode),
            "responseSchema" => $this->toResponseSchema($responseFormat, $mode),
            "candidateCount" => 1,
            "maxOutputTokens" => $options['max_tokens'] ?? $this->config->maxTokens,
            "temperature" => $options['temperature'] ?? 1.0,
        ]);
    }

    protected function toTools(array $tools) : array {
        return ['function_declarations' => array_map(
            callback: fn($tool) => $this->removeDisallowedEntries($tool['function']),
            array: $tools
        )];
    }

    protected function toToolChoice(string|array $toolChoice): string|array {
        return match(true) {
            empty($toolChoice) => ["function_calling_config" => ["mode" => "ANY"]],
            is_string($toolChoice) => ["function_calling_config" => ["mode" => $this->mapToolChoice($toolChoice)]],
            is_array($toolChoice) => [
                "function_calling_config" => array_filter([
                    "mode" => $this->mapToolChoice($toolChoice['mode'] ?? "ANY"),
                    "allowed_function_names" => $toolChoice['function']['name'] ?? [],
                ]),
            ],
            default => ["function_calling_config" => ["mode" => "ANY"]],
        };
    }

    protected function mapToolChoice(string $choice) : string {
        return match($choice) {
            'auto' => 'AUTO',
            'required' => 'ANY',
            'none' => 'NONE',
            default => 'ANY',
        };
    }

    protected function toResponseMimeType(Mode $mode): string {
        return match($mode) {
            Mode::Text => "text/plain",
            Mode::MdJson => "text/plain",
            Mode::Tools => "text/plain",
            Mode::Json => "application/json",
            Mode::JsonSchema => "application/json",
            default => "application/json",
        };
    }

    protected function toResponseSchema(array $responseFormat, Mode $mode) : array {
        return $this->removeDisallowedEntries($responseFormat['schema'] ?? []);
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'x-title',
            'x-php-class',
            'additionalProperties',
        ]);
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
            'parts' => $this->toNativeContentParts($message['content']),
        ];
    }

    private function toNativeToolCall(array $message) : array {
        return [
            'role' => 'model',
            'parts' => array_map(
                callback: fn($call) => $this->toNativeToolCallPart($call),
                array: $message['_metadata']['tool_calls'] ?? []
            ),
        ];
    }

    private function toNativeToolCallPart(array $call) : array {
        return [
            'functionCall' => [
                'name' => $call['function']['name'] ?? '',
                'args' => Json::from($call['function']['arguments'])->toArray() ?? [],
            ]
        ];
    }

    private function toNativeToolResult(array $message) : array {
        $content = match(true) {
            is_array($message['_metadata']['result'] ?? '') => Json::from($message['_metadata']['result'] ?? '')->toArray(),
            default => $message['content'],
        };
        return [
            'role' => 'user',
            'parts' => [[
                'functionResponse' => [
                    'name' => $message['_metadata']['tool_name'] ?? '',
                    'response' => [
                        'name' => $message['_metadata']['tool_name'] ?? '',
                        'content' => $content,
                    ],
                ],
            ]],
        ];
    }

    protected function mapRole(string $role) : string {
        $roles = ['user' => 'user', 'assistant' => 'model', 'system' => 'user', 'tool' => 'tool'];
        return $roles[$role] ?? $role;
    }

    protected function toNativeContentParts(string|array $contentParts) : array {
        if (is_string($contentParts)) {
            return [["text" => $contentParts]];
        }
        $transformed = [];
        foreach ($contentParts as $contentPart) {
            $transformed[] = $this->contentPartToNative($contentPart);
        }
        return $transformed;
    }

    protected function contentPartToNative(array $contentPart) : array {
        $type = $contentPart['type'] ?? 'text';
        return match(true) {
            ($type === 'text') => $this->makeTextContentPart($contentPart),
            ($type === 'image_url') => $this->makeImageUrlContentPart($contentPart),

            default => $contentPart,
        };
    }

    private function makeTextContentPart(array $contentPart) : array {
        return ['text' => $contentPart['text'] ?? ''];
    }

    private function makeImageUrlContentPart(array $contentPart) : array {
        return [
            'inlineData' => [
                'mimeType' => Str::between($contentPart['image_url']['url'], 'data:', ';base64,'),
                'data' => Str::after($contentPart['image_url']['url'], ';base64,'),
            ],
        ];
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(array_map(
            callback: fn(array $call) => $call['functionCall'] ?? [],
            array: $data['candidates'][0]['content']['parts'] ?? []
        ), fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['args'] ?? '']));
    }

    private function makeContent(array $data) : string {
        $partCount = count($data['candidates'][0]['content']['parts'] ?? []);
        if ($partCount === 1) {
            return $this->makeContentPart($data, 0);
        }
        $content = '';
        for ($i = 0; $i < $partCount; $i++) {
            $part = $this->makeContentPart($data, $i) . "\n\n";
            $content .= $part;
        }
        return $content;
    }

    private function makeContentPart(array $data, int $index) : string {
        return $data['candidates'][0]['content']['parts'][$index]['text']
            ?? Json::encode($data['candidates'][0]['content']['parts'][$index]['functionCall']['args'] ?? '')
            ?? '';
    }

    private function makeContentDelta(array $data): string {
        $partCount = count($data['candidates'][0]['content']['parts'] ?? []);
        if ($partCount === 1) {
            return  $this->makeContentDeltaPart($data, 0);
        }

        $content = '';
        for ($i = 0; $i < $partCount; $i++) {
            $part = $this->makeContentDeltaPart($data, $i) . "\n";
            $content .= $part;
        }
        return $content;
    }

    private function makeContentDeltaPart(array $data, int $index) : string {
        return $data['candidates'][0]['content']['parts'][$index]['text']
            ?? Json::encode($data['candidates'][0]['content']['parts'][$index]['functionCall']['args'] ?? '')
            ?? '';
    }

    private function makeToolName(array $data) : string {
        return $data['candidates'][0]['content']['parts'][0]['functionCall']['name'] ?? '';
    }

    private function makeToolArgs(array $data) : string {
        $value = $data['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? '';
        return is_array($value) ? Json::encode($value) : '';
    }

    private function makeUsage(array $data) : Usage {
        return new Usage(
            inputTokens: $data['usageMetadata']['promptTokenCount'] ?? 0,
            outputTokens: $data['usageMetadata']['candidatesTokenCount'] ?? 0,
            cacheWriteTokens: 0,
            cacheReadTokens: 0,
            reasoningTokens: 0,
        );
    }
}
