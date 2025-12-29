<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Support;

use Cognesy\Config\Contracts\CanProvideConfig;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel Config Provider
 *
 * Bridges Laravel's configuration system to Instructor's config provider interface.
 * Maps Laravel's instructor.php config to Instructor's expected preset format.
 */
final class LaravelConfigProvider implements CanProvideConfig
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Get configuration value by path.
     *
     * Supports both:
     * - Direct path: 'instructor.default'
     * - Preset path: 'llm.presets.openai' (maps to instructor.connections.openai)
     */
    #[\Override]
    public function get(string $path, mixed $default = null): mixed
    {
        // Handle preset lookups (e.g., 'llm.presets.openai')
        if (str_starts_with($path, 'llm.')) {
            return $this->getLlmConfig($path, $default);
        }

        if (str_starts_with($path, 'embed.')) {
            return $this->getEmbedConfig($path, $default);
        }

        if (str_starts_with($path, 'http.')) {
            return $this->getHttpConfig($path, $default);
        }

        // Direct config access
        return $this->app['config']->get($path, $default);
    }

    /**
     * Check if configuration path exists.
     */
    #[\Override]
    public function has(string $path): bool
    {
        if (str_starts_with($path, 'llm.')) {
            return $this->hasLlmConfig($path);
        }

        if (str_starts_with($path, 'embed.')) {
            return $this->hasEmbedConfig($path);
        }

        if (str_starts_with($path, 'http.')) {
            return $this->hasHttpConfig($path);
        }

        return $this->app['config']->has($path);
    }

    /**
     * Get LLM preset configuration.
     */
    private function getLlmConfig(string $path, mixed $default): mixed
    {
        // Handle 'llm.presets' or 'llm.presets.{name}'
        if ($path === 'llm' || $path === 'llm.presets') {
            return $this->buildAllLlmPresets();
        }

        if (str_starts_with($path, 'llm.presets.')) {
            $presetName = substr($path, strlen('llm.presets.'));
            return $this->buildLlmPreset($presetName);
        }

        if ($path === 'llm.defaultPreset') {
            return $this->app['config']->get('instructor.default', 'openai');
        }

        return $default;
    }

    /**
     * Get embeddings preset configuration.
     */
    private function getEmbedConfig(string $path, mixed $default): mixed
    {
        if ($path === 'embed' || $path === 'embed.presets') {
            return $this->buildAllEmbedPresets();
        }

        if (str_starts_with($path, 'embed.presets.')) {
            $presetName = substr($path, strlen('embed.presets.'));
            return $this->buildEmbedPreset($presetName);
        }

        if ($path === 'embed.defaultPreset') {
            return $this->app['config']->get('instructor.embeddings.default', 'openai');
        }

        return $default;
    }

    /**
     * Get HTTP configuration.
     */
    private function getHttpConfig(string $path, mixed $default): mixed
    {
        if ($path === 'http' || $path === 'http.presets') {
            return $this->buildHttpPresets();
        }

        return $default;
    }

    /**
     * Check if LLM config exists.
     */
    private function hasLlmConfig(string $path): bool
    {
        if ($path === 'llm' || $path === 'llm.presets' || $path === 'llm.defaultPreset') {
            return true;
        }

        if (str_starts_with($path, 'llm.presets.')) {
            $presetName = substr($path, strlen('llm.presets.'));
            return $this->app['config']->has("instructor.connections.{$presetName}");
        }

        return false;
    }

    /**
     * Check if embed config exists.
     */
    private function hasEmbedConfig(string $path): bool
    {
        if ($path === 'embed' || $path === 'embed.presets' || $path === 'embed.defaultPreset') {
            return true;
        }

        if (str_starts_with($path, 'embed.presets.')) {
            $presetName = substr($path, strlen('embed.presets.'));
            return $this->app['config']->has("instructor.embeddings.connections.{$presetName}");
        }

        return false;
    }

    /**
     * Check if HTTP config exists.
     */
    private function hasHttpConfig(string $path): bool
    {
        return $path === 'http' || $path === 'http.presets';
    }

    /**
     * Build all LLM presets from Laravel config.
     */
    private function buildAllLlmPresets(): array
    {
        $connections = $this->app['config']->get('instructor.connections', []);
        $presets = [];

        foreach ($connections as $name => $config) {
            $presets[$name] = $this->normalizeConnectionConfig($config);
        }

        return [
            'defaultPreset' => $this->app['config']->get('instructor.default', 'openai'),
            'presets' => $presets,
        ];
    }

    /**
     * Build a single LLM preset.
     */
    private function buildLlmPreset(string $name): ?array
    {
        $config = $this->app['config']->get("instructor.connections.{$name}");

        if ($config === null) {
            return null;
        }

        return $this->normalizeConnectionConfig($config);
    }

    /**
     * Build all embed presets from Laravel config.
     */
    private function buildAllEmbedPresets(): array
    {
        $connections = $this->app['config']->get('instructor.embeddings.connections', []);
        $presets = [];

        foreach ($connections as $name => $config) {
            $presets[$name] = $this->normalizeEmbedConfig($config);
        }

        return [
            'defaultPreset' => $this->app['config']->get('instructor.embeddings.default', 'openai'),
            'presets' => $presets,
        ];
    }

    /**
     * Build a single embed preset.
     */
    private function buildEmbedPreset(string $name): ?array
    {
        $config = $this->app['config']->get("instructor.embeddings.connections.{$name}");

        if ($config === null) {
            return null;
        }

        return $this->normalizeEmbedConfig($config);
    }

    /**
     * Build HTTP presets.
     */
    private function buildHttpPresets(): array
    {
        $http = $this->app['config']->get('instructor.http', []);

        return [
            'defaultPreset' => 'default',
            'presets' => [
                'default' => [
                    'driver' => $http['driver'] ?? 'laravel',
                    'requestTimeout' => $http['timeout'] ?? 120,
                    'connectTimeout' => $http['connect_timeout'] ?? 30,
                ],
            ],
        ];
    }

    /**
     * Normalize Laravel connection config to Instructor preset format.
     */
    private function normalizeConnectionConfig(array $config): array
    {
        return [
            'driver' => $config['driver'] ?? 'openai',
            'apiUrl' => $config['api_url'] ?? $this->getDefaultApiUrl($config['driver'] ?? 'openai'),
            'apiKey' => $config['api_key'] ?? '',
            'endpoint' => $config['endpoint'] ?? '/chat/completions',
            'model' => $config['model'] ?? '',
            'maxTokens' => $config['max_tokens'] ?? 4096,
            'metadata' => $this->buildMetadata($config),
        ];
    }

    /**
     * Normalize embed connection config.
     */
    private function normalizeEmbedConfig(array $config): array
    {
        return [
            'driver' => $config['driver'] ?? 'openai',
            'apiUrl' => $config['api_url'] ?? $this->getDefaultApiUrl($config['driver'] ?? 'openai'),
            'apiKey' => $config['api_key'] ?? '',
            'endpoint' => $config['endpoint'] ?? '/embeddings',
            'model' => $config['model'] ?? '',
            'dimensions' => $config['dimensions'] ?? 1536,
        ];
    }

    /**
     * Build metadata from config.
     */
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

    /**
     * Get default API URL for a driver.
     */
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
}
