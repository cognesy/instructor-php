<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Support;

use Cognesy\Config\Contracts\CanProvideConfig;
use Illuminate\Contracts\Container\Container;

/**
 * Bridges Laravel configuration to Instructor's edge-facing config provider contract.
 */
final class LaravelConfigProvider implements CanProvideConfig
{
    public function __construct(
        private readonly Container $app,
    ) {}

    #[\Override]
    public function get(string $path, mixed $default = null): mixed
    {
        $normalizedPath = $this->normalizePath($path);

        if (str_starts_with($normalizedPath, 'llm.')) {
            return $this->getLLMConfig($normalizedPath, $default);
        }

        if (str_starts_with($normalizedPath, 'embed.')) {
            return $this->getEmbedConfig($normalizedPath, $default);
        }

        if (str_starts_with($normalizedPath, 'http.')) {
            return $this->getHttpConfig($normalizedPath, $default);
        }

        return $this->config()->get($normalizedPath, $default);
    }

    #[\Override]
    public function has(string $path): bool
    {
        $normalizedPath = $this->normalizePath($path);

        if (str_starts_with($normalizedPath, 'llm.')) {
            return $this->hasLLMConfig($normalizedPath);
        }

        if (str_starts_with($normalizedPath, 'embed.')) {
            return $this->hasEmbedConfig($normalizedPath);
        }

        if (str_starts_with($normalizedPath, 'http.')) {
            return $this->hasHttpConfig($normalizedPath);
        }

        return $this->config()->has($normalizedPath);
    }

    private function getLLMConfig(string $path, mixed $default): mixed
    {
        if ($path === 'llm') {
            return [
                'default' => $this->llmDefaultConnection(),
                'connections' => $this->llmConnections(),
            ];
        }

        if ($path === 'llm.default') {
            return $this->llmDefaultConnection();
        }

        if ($path === 'llm.connections') {
            return $this->llmConnections();
        }

        if (!str_starts_with($path, 'llm.connections.')) {
            return $default;
        }

        $connection = substr($path, strlen('llm.connections.'));
        return $this->llmConnections()[$connection] ?? $default;
    }

    private function getEmbedConfig(string $path, mixed $default): mixed
    {
        if ($path === 'embed') {
            return [
                'default' => $this->embedDefaultConnection(),
                'connections' => $this->embedConnections(),
            ];
        }

        if ($path === 'embed.default') {
            return $this->embedDefaultConnection();
        }

        if ($path === 'embed.connections') {
            return $this->embedConnections();
        }

        if (!str_starts_with($path, 'embed.connections.')) {
            return $default;
        }

        $connection = substr($path, strlen('embed.connections.'));
        return $this->embedConnections()[$connection] ?? $default;
    }

    private function getHttpConfig(string $path, mixed $default): mixed
    {
        if ($path === 'http') {
            return [
                'default' => 'default',
                'connections' => [
                    'default' => $this->httpConnection(),
                ],
            ];
        }

        if ($path === 'http.default') {
            return 'default';
        }

        if ($path === 'http.connections') {
            return ['default' => $this->httpConnection()];
        }

        if ($path === 'http.connections.default') {
            return $this->httpConnection();
        }

        return $default;
    }

    private function hasLLMConfig(string $path): bool
    {
        if ($path === 'llm' || $path === 'llm.default' || $path === 'llm.connections') {
            return true;
        }

        if (!str_starts_with($path, 'llm.connections.')) {
            return false;
        }

        $connection = substr($path, strlen('llm.connections.'));
        return array_key_exists($connection, $this->llmConnections());
    }

    private function hasEmbedConfig(string $path): bool
    {
        if ($path === 'embed' || $path === 'embed.default' || $path === 'embed.connections') {
            return true;
        }

        if (!str_starts_with($path, 'embed.connections.')) {
            return false;
        }

        $connection = substr($path, strlen('embed.connections.'));
        return array_key_exists($connection, $this->embedConnections());
    }

    private function hasHttpConfig(string $path): bool
    {
        return in_array($path, ['http', 'http.default', 'http.connections', 'http.connections.default'], true);
    }

    private function llmDefaultConnection(): string
    {
        return (string) $this->config()->get('instructor.default', 'openai');
    }

    private function embedDefaultConnection(): string
    {
        return (string) $this->config()->get('instructor.embeddings.default', 'openai');
    }

    private function llmConnections(): array
    {
        $raw = $this->config()->get('instructor.connections', []);
        if (!is_array($raw)) {
            return [];
        }

        $connections = [];
        foreach ($raw as $name => $config) {
            if (!is_array($config)) {
                continue;
            }
            $connections[(string) $name] = $this->normalizeConnectionConfig($config);
        }
        return $connections;
    }

    private function embedConnections(): array
    {
        $raw = $this->config()->get('instructor.embeddings.connections', []);
        if (!is_array($raw)) {
            return [];
        }

        $connections = [];
        foreach ($raw as $name => $config) {
            if (!is_array($config)) {
                continue;
            }
            $connections[(string) $name] = $this->normalizeEmbedConfig($config);
        }
        return $connections;
    }

    private function httpConnection(): array
    {
        $http = $this->config()->get('instructor.http', []);
        $config = is_array($http) ? $http : [];

        return [
            'driver' => $config['driver'] ?? 'laravel',
            'requestTimeout' => $config['timeout'] ?? 120,
            'connectTimeout' => $config['connect_timeout'] ?? 30,
        ];
    }

    private function normalizeConnectionConfig(array $config): array
    {
        $driver = (string) ($config['driver'] ?? 'openai');

        return [
            'driver' => $driver,
            'apiUrl' => $config['api_url'] ?? $this->getDefaultApiUrl($driver),
            'apiKey' => $config['api_key'] ?? '',
            'endpoint' => $config['endpoint'] ?? '/chat/completions',
            'model' => $config['model'] ?? '',
            'maxTokens' => $config['max_tokens'] ?? 4096,
            'metadata' => $this->buildMetadata($config),
        ];
    }

    private function normalizeEmbedConfig(array $config): array
    {
        $driver = (string) ($config['driver'] ?? 'openai');

        return [
            'driver' => $driver,
            'apiUrl' => $config['api_url'] ?? $this->getDefaultApiUrl($driver),
            'apiKey' => $config['api_key'] ?? '',
            'endpoint' => $config['endpoint'] ?? '/embeddings',
            'model' => $config['model'] ?? '',
            'dimensions' => $config['dimensions'] ?? 1536,
        ];
    }

    private function buildMetadata(array $config): array
    {
        $metadata = [];

        if (isset($config['organization'])) {
            $metadata['organization'] = $config['organization'];
        }

        if (isset($config['resource_name'])) {
            $metadata['resourceName'] = $config['resource_name'];
        }

        if (isset($config['deployment_id'])) {
            $metadata['deploymentId'] = $config['deployment_id'];
        }

        if (isset($config['api_version'])) {
            $metadata['apiVersion'] = $config['api_version'];
        }

        return $metadata;
    }

    private function getDefaultApiUrl(string $driver): string
    {
        return match ($driver) {
            'openai' => 'https://api.openai.com/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            'azure' => 'https://{resourceName}.openai.azure.com/openai/deployments/{deploymentId}',
            'gemini' => 'https://generativelanguage.googleapis.com/v1beta',
            'gemini-oai' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'mistral' => 'https://api.mistral.ai/v1',
            'groq' => 'https://api.groq.com/openai/v1',
            'cohere' => 'https://api.cohere.ai/v2',
            'deepseek' => 'https://api.deepseek.com',
            'ollama' => 'http://localhost:11434/v1',
            'perplexity' => 'https://api.perplexity.ai',
            default => '',
        };
    }

    private function normalizePath(string $path): string
    {
        if (str_contains($path, '.presets.')) {
            return str_replace('.presets.', '.connections.', $path);
        }

        if (str_ends_with($path, '.presets')) {
            return str_replace('.presets', '.connections', $path);
        }

        if (str_ends_with($path, '.defaultPreset')) {
            return str_replace('.defaultPreset', '.default', $path);
        }

        return $path;
    }

    private function config(): object
    {
        return $this->app->make('config');
    }
}
