<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\Anthropic;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Str;

class AnthropicRequestAdapter implements ProviderRequestAdapter
{
    private bool $parallelToolCalls = false;

    public function __construct(
        protected LLMConfig $config,
    ) {}

    public function toHeaders(): array {
        return array_filter([
            'x-api-key' => $this->config->apiKey,
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
    }

    public function toUrl(string $model = '', bool $stream = false): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    public function toRequestBody(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): array {
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

    // INTERNAL /////////////////////////////////////////////

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
}