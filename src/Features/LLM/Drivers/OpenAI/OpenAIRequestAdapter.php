<?php

namespace Cognesy\Instructor\Features\LLM\Drivers\OpenAI;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Contracts\ProviderRequestAdapter;
use Cognesy\Instructor\Features\LLM\Data\LLMConfig;

class OpenAIRequestAdapter implements ProviderRequestAdapter
{
    public function __construct(
        protected LLMConfig $config,
    ) {}

    public function toHeaders(): array {
        $extras = array_filter([
            "OpenAI-Organization" => $this->config->metadata['organization'] ?? '',
            "OpenAI-Project" => $this->config->metadata['project'] ?? '',
        ]);
        return array_merge([
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ], $extras);
    }

    public function toUrl(string $model = '', bool $stream = false): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    public function toRequestBody(array $messages, string $model, array $tools, array|string $toolChoice, array $responseFormat, array $options, Mode $mode): array {
        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $this->toNativeMessages($messages),
        ]), $options);

        if ($options['stream'] ?? false) {
            $request['stream_options']['include_usage'] = true;
        }

        if (!empty($tools)) {
            $request['tools'] = $tools;
            $request['tool_choice'] = $toolChoice;
        }

        return $this->applyMode($request, $mode, $tools, $toolChoice, $responseFormat);
    }

    private function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ) : array {
        switch($mode) {
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

    protected function toNativeMessages(array $messages) : array {
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

    protected function mapMessage(array $message) : array {
        return match(true) {
            ($message['role'] ?? '') === 'assistant' && !empty($message['_metadata']['tool_calls'] ?? []) => $this->toNativeToolCall($message),
            ($message['role'] ?? '') === 'tool' => $this->toNativeToolResult($message),
            default => $message,
        };
    }

    protected function toNativeToolCall(array $message) : array {
        return [
            'role' => 'assistant',
            'tool_calls' => $message['_metadata']['tool_calls'] ?? [],
        ];
    }

    protected function toNativeToolResult(array $message) : array {
        return [
            'role' => 'tool',
            'tool_call_id' => $message['_metadata']['tool_call_id'] ?? '',
            'content' => $message['content'] ?? '',
        ];
    }
}