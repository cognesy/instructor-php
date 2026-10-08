<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\BatchInference\Transport\BatchTransportUrl;
use InvalidArgumentException;

final readonly class BatchConfig
{
    public function __construct(
        private LLMConfig $inference,
        private string $provider,
        private string $apiBaseUrl,
        private string $scope,
        private string $route,
        private string $codec,
    ) {
        if ($provider === '' || $scope === '' || $route === '' || $codec === '' || $apiBaseUrl === '') {
            throw new InvalidArgumentException('Batch provider, API base URL, scope, route, and codec are required.');
        }
        BatchTransportUrl::assertSecure($apiBaseUrl);
    }

    public static function fromLLMConfig(LLMConfig $config): self
    {
        [$provider, $apiBase, $route, $codec] = match ($config->driver) {
            'openai' => ['openai', 'https://api.openai.com/v1', '/v1/chat/completions', 'openai-chat'],
            'openai-responses' => ['openai', 'https://api.openai.com/v1', '/v1/responses', 'openai-responses'],
            'anthropic' => ['anthropic', 'https://api.anthropic.com/v1', '/v1/messages', 'anthropic-messages'],
            'mistral' => ['mistral', 'https://api.mistral.ai/v1', '/v1/chat/completions', 'mistral-chat'],
            'groq' => ['groq', 'https://api.groq.com/openai/v1', '/v1/chat/completions', 'groq-chat'],
            'together' => ['together', 'https://api.together.ai/v1', '/v1/chat/completions', 'together-chat'],
            'qwen' => ['qwen', rtrim($config->apiUrl, '/'), '/v1/chat/completions', 'qwen-chat'],
            'gemini' => ['gemini', 'https://generativelanguage.googleapis.com/v1beta', 'models/{model}:batchGenerateContent', 'gemini-generate-content'],
            'xai' => ['xai', 'https://api.x.ai/v1', '/v1/chat/completions', 'xai-chat'],
            default => throw new InvalidArgumentException("No native batch configuration for inference driver {$config->driver}."),
        };
        $acceptedInferenceBases = match ($provider) {
            'together' => ['https://api.together.xyz/v1', 'https://api.together.ai/v1'],
            'qwen' => [
                'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
                'https://dashscope.aliyuncs.com/compatible-mode/v1',
            ],
            default => [$apiBase],
        };
        if (!in_array(rtrim($config->apiUrl, '/'), $acceptedInferenceBases, true)) {
            throw new InvalidArgumentException('A custom inference API URL needs an explicit BatchConfig with a verified batch control-plane URL.');
        }

        $scope = $provider.'|'.$apiBase.'|'.(string) ($config->metadata['project'] ?? $config->metadata['workspace'] ?? '');

        return new self($config, $provider, $apiBase, $scope, $route, $codec);
    }

    public function inference(): LLMConfig
    {
        return $this->inference;
    }
    public function provider(): string
    {
        return $this->provider;
    }
    public function apiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }
    public function scope(): string
    {
        return $this->scope;
    }
    public function route(): string
    {
        return $this->route;
    }
    public function codec(): string
    {
        return $this->codec;
    }
}
