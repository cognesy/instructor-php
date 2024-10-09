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

        if ($mode == Mode::Tools) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_config'] = $this->toToolChoice($toolChoice);
        }
        return $request;
    }

    // RESPONSE /////////////////////////////////////////////

    public function toLLMResponse(array $data): ?LLMResponse {
        return new LLMResponse(
            content: $this->makeContent($data),
            responseData: $data,
//            toolName: $data['candidates'][0]['content']['parts'][0]['functionCall']['name'] ?? '',
//            toolArgs: Json::encode($data['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? []),
            toolsData: $this->mapToolsData($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
            toolCalls: $this->makeToolCalls($data),
            usage: $this->makeUsage($data),
        );
    }

    public function toPartialLLMResponse(array $data) : ?PartialLLMResponse {
        if (empty($data)) {
            return null;
        }
        return new PartialLLMResponse(
            contentDelta: $this->makeContentDelta($data),
            responseData: $data,
            toolName: $this->makeToolName($data),
            toolArgs: $this->makeToolArgs($data),
            finishReason: $data['candidates'][0]['finishReason'] ?? '',
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

    private function toSystem(array $messages) : array {
        $system = Messages::fromArray($messages)
            ->forRoles(['system'])
            ->toString();

        return empty($system) ? [] : ['parts' => ['text' => $system]];
    }

    private function toMessages(array $messages) : array {
        return $this->toNativeMessages(Messages::fromArray($messages)
            ->exceptRoles(['system'])
            //->toMergedPerRole()
            ->toArray());
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

    protected function toToolChoice(array $toolChoice): string|array {
        return match(true) {
            empty($toolChoice) => ["function_calling_config" => ["mode" => "ANY"]],
            is_array($toolChoice) => [
                "function_calling_config" => array_filter([
                    "mode" => "ANY",
                    "allowed_function_names" => $toolChoice['function']['name'] ?? [],
                ]),
            ],
            default => ["function_calling_config" => ["mode" => "ANY"]],
        };
    }

    protected function toResponseMimeType(Mode $mode): string {
        return match($mode) {
            Mode::Text => "text/plain",
            Mode::MdJson => "text/plain",
            Mode::Tools => "text/plain",
            default => "application/json",
        };
    }

    protected function toResponseSchema(array $responseFormat, Mode $mode) : array {
        return match($mode) {
            Mode::MdJson => $this->removeDisallowedEntries($responseFormat['schema'] ?? []),
            Mode::Json => $this->removeDisallowedEntries($responseFormat['schema'] ?? []),
            Mode::JsonSchema => $this->removeDisallowedEntries($responseFormat['schema'] ?? []),
            default => [],
        };
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    protected function toNativeMessages(string|array $messages) : array {
        if (is_string($messages)) {
            return [["text" => $messages]];
        }
        $transformed = [];
        foreach ($messages as $message) {
            $transformed[] = [
                'role' => $this->mapRole($message['role']),
                'parts' => $this->contentPartsToNative($message['content']),
            ];
        }
        return $transformed;
    }

    protected function mapRole(string $role) : string {
        $roles = ['user' => 'user', 'assistant' => 'model', 'system' => 'user', 'tool' => 'tool'];
        return $roles[$role] ?? $role;
    }

    protected function contentPartsToNative(string|array $contentParts) : array {
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
        return match($type) {
            'text' => ['text' => $contentPart['text'] ?? ''],
            'image_url' => [
                'inlineData' => [
                    'mimeType' => Str::between($contentPart['image_url']['url'], 'data:', ';base64,'),
                    'data' => Str::after($contentPart['image_url']['url'], ';base64,'),
                ],
            ],
            default => $contentPart,
        };
    }

    private function makeToolCalls(array $data) : ToolCalls {
        return ToolCalls::fromMapper(array_map(
            callback: fn(array $call) => $call['functionCall'] ?? [],
            array: $data['candidates'][0]['content']['parts'] ?? []
        ), fn($call) => ToolCall::fromArray(['name' => $call['name'] ?? '', 'arguments' => $call['args'] ?? '']));
    }

    private function mapToolsData(array $data) : array {
        return array_map(
            fn($tool) => [
                'name' => $tool['functionCall']['name'] ?? '',
                'arguments' => $tool['functionCall']['args'] ?? '',
            ],
            $data['candidates'][0]['content']['parts'] ?? []
        );
    }

    private function makeContent(array $data) : string {
        return $data['candidates'][0]['content']['parts'][0]['text']
            ?? Json::encode($data['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? '')
            ?? '';
    }

    private function makeContentDelta(array $data): string {
        return $data['candidates'][0]['content']['parts'][0]['text']
            ?? Json::encode($data['candidates'][0]['content']['parts'][0]['functionCall']['args'] ?? '')
            ?? '';
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
