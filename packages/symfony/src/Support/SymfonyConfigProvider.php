<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Support;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

final class SymfonyConfigProvider implements CanProvideConfig
{
    /** @var list<string> */
    private const CONNECTION_META_KEYS = ['default', 'items', 'connections'];

    /** @var list<string> */
    private const LLM_RESERVED_KEYS = [
        'driver',
        'api_url',
        'apiUrl',
        'api_key',
        'apiKey',
        'endpoint',
        'model',
        'max_tokens',
        'maxTokens',
        'context_length',
        'contextLength',
        'max_output_length',
        'maxOutputLength',
        'query_params',
        'queryParams',
        'metadata',
        'options',
        'pricing',
        'organization',
        'project',
        'resource_name',
        'resourceName',
        'deployment_id',
        'deploymentId',
        'api_version',
        'apiVersion',
        'beta',
        'client_name',
        'clientName',
        'region',
        'guardrail_id',
        'guardrailId',
        'guardrail_version',
        'guardrailVersion',
        'open_responses_version',
        'openResponsesVersion',
    ];

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
    ) {}

    #[\Override]
    public function get(string $path, mixed $default = null): mixed
    {
        $sentinel = new \stdClass;
        $value = $this->lookup($path, $sentinel);

        return match (true) {
            $value === $sentinel => $default,
            default => $value,
        };
    }

    #[\Override]
    public function has(string $path): bool
    {
        $sentinel = new \stdClass;

        return $this->lookup($path, $sentinel) !== $sentinel;
    }

    public function llm(string $connection = ''): LLMConfig
    {
        $name = $connection !== '' ? $connection : $this->llmDefaultConnection();
        $config = $this->llmConnections()[$name] ?? $this->normalizeLlmConnection($name, []);

        return LLMConfig::fromArray($config);
    }

    public function embeddings(string $connection = ''): EmbeddingsConfig
    {
        $name = $connection !== '' ? $connection : $this->embeddingsDefaultConnection();
        $config = $this->embeddingsConnections()[$name] ?? $this->normalizeEmbeddingsConnection($name, []);

        return EmbeddingsConfig::fromArray($config);
    }

    public function structuredOutput(): StructuredOutputConfig
    {
        return StructuredOutputConfig::fromArray($this->structuredOutputData());
    }

    public function httpClient(): HttpClientConfig
    {
        return HttpClientConfig::fromArray($this->httpClientData());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->config;
    }

    private function lookup(string $path, object $sentinel): mixed
    {
        $normalizedPath = $this->normalizePath($path);

        return match (true) {
            $normalizedPath === 'instructor' => $this->config,
            str_starts_with($normalizedPath, 'instructor.') => $this->readPath(['instructor' => $this->config], $normalizedPath, $sentinel),
            str_starts_with($normalizedPath, 'llm') => $this->readPath(['llm' => $this->llmView()], $normalizedPath, $sentinel),
            str_starts_with($normalizedPath, 'embed') => $this->readPath(['embed' => $this->embeddingsView()], $normalizedPath, $sentinel),
            str_starts_with($normalizedPath, 'http') => $this->readPath(['http' => $this->httpView()], $normalizedPath, $sentinel),
            str_starts_with($normalizedPath, 'structured') => $this->readPath(['structured' => $this->structuredView()], $normalizedPath, $sentinel),
            default => $this->readPath($this->config, $normalizedPath, $sentinel),
        };
    }

    /** @return array<string, mixed> */
    private function llmView(): array
    {
        return [
            'default' => $this->llmDefaultConnection(),
            'connections' => $this->llmConnections(),
        ];
    }

    /** @return array<string, mixed> */
    private function embeddingsView(): array
    {
        return [
            'default' => $this->embeddingsDefaultConnection(),
            'connections' => $this->embeddingsConnections(),
        ];
    }

    /** @return array<string, mixed> */
    private function httpView(): array
    {
        return [
            'default' => 'default',
            'connections' => [
                'default' => $this->httpClientData(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function structuredView(): array
    {
        return $this->structuredOutput()->toArray();
    }

    private function llmDefaultConnection(): string
    {
        $root = $this->connectionsRoot();
        $default = $this->stringValue($root, 'default');
        if ($default !== '') {
            return $default;
        }

        $legacyDefault = $this->stringValue($this->config, 'default');
        if ($legacyDefault !== '') {
            return $legacyDefault;
        }

        return $this->firstConnectionName($this->rawConnectionEntries($root), 'openai');
    }

    private function embeddingsDefaultConnection(): string
    {
        $root = $this->embeddingsRoot();
        $default = $this->stringValue($root, 'default');
        if ($default !== '') {
            return $default;
        }

        return $this->firstConnectionName($this->rawConnectionEntries($root), 'openai');
    }

    /** @return array<string, array<string, mixed>> */
    private function llmConnections(): array
    {
        $connections = [];

        foreach ($this->rawConnectionEntries($this->connectionsRoot()) as $name => $config) {
            $connections[(string) $name] = $this->normalizeLlmConnection((string) $name, $config);
        }

        return $connections;
    }

    /** @return array<string, array<string, mixed>> */
    private function embeddingsConnections(): array
    {
        $connections = [];

        foreach ($this->rawConnectionEntries($this->embeddingsRoot()) as $name => $config) {
            $connections[(string) $name] = $this->normalizeEmbeddingsConnection((string) $name, $config);
        }

        return $connections;
    }

    /** @return array<string, mixed> */
    private function normalizeLlmConnection(string $name, array $config): array
    {
        $driver = $this->nonEmptyString($this->stringValue($config, 'driver'), $name, 'openai');
        $model = $this->stringValue($config, 'model');
        $endpoint = $this->stringValue($config, 'endpoint');
        $apiUrl = $this->stringValue($config, 'apiUrl', 'api_url');

        return [
            'driver' => $driver,
            'apiUrl' => $apiUrl !== '' ? $apiUrl : $this->defaultApiUrl($driver),
            'apiKey' => $this->stringValue($config, 'apiKey', 'api_key'),
            'endpoint' => $endpoint !== '' ? $endpoint : $this->defaultLlmEndpoint($driver, $model),
            'queryParams' => $this->arrayValue($config, 'queryParams', 'query_params'),
            'metadata' => $this->llmMetadata($config),
            'model' => $model,
            'maxTokens' => $this->intValue($config, 4096, 'maxTokens', 'max_tokens'),
            'contextLength' => $this->intValue($config, 8000, 'contextLength', 'context_length'),
            'maxOutputLength' => $this->intValue($config, 4096, 'maxOutputLength', 'max_output_length'),
            'options' => $this->llmOptions($config),
            'pricing' => $this->arrayValue($config, 'pricing'),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeEmbeddingsConnection(string $name, array $config): array
    {
        $driver = $this->nonEmptyString($this->stringValue($config, 'driver'), $name, 'openai');
        $apiUrl = $this->stringValue($config, 'apiUrl', 'api_url');
        $endpoint = $this->stringValue($config, 'endpoint');

        return [
            'driver' => $driver,
            'apiUrl' => $apiUrl !== '' ? $apiUrl : $this->defaultApiUrl($driver),
            'apiKey' => $this->stringValue($config, 'apiKey', 'api_key'),
            'endpoint' => $endpoint !== '' ? $endpoint : '/embeddings',
            'model' => $this->stringValue($config, 'model'),
            'dimensions' => $this->intValue($config, 0, 'dimensions', 'defaultDimensions', 'default_dimensions'),
            'maxInputs' => $this->intValue($config, 0, 'maxInputs', 'max_inputs'),
            'metadata' => $this->embeddingsMetadata($config),
        ];
    }

    /** @return array<string, mixed> */
    private function structuredOutputData(): array
    {
        $config = $this->extractionRoot();
        $data = [];

        $outputMode = $this->normalizeOutputMode($config);
        if ($outputMode !== '') {
            $data['outputMode'] = $outputMode;
        }

        $this->assignBool($data, 'useObjectReferences', $config, 'useObjectReferences', 'use_object_references');
        $this->assignInt($data, 'maxRetries', $config, 'maxRetries', 'max_retries');
        $this->assignString($data, 'retryPrompt', $config, 'retryPrompt', 'retry_prompt');
        $this->assignArray($data, 'modePrompts', $config, 'modePrompts', 'mode_prompts');
        $this->assignArray($data, 'modePromptClasses', $config, 'modePromptClasses', 'mode_prompt_classes');
        $this->assignString($data, 'retryPromptClass', $config, 'retryPromptClass', 'retry_prompt_class');
        $this->assignString($data, 'schemaName', $config, 'schemaName', 'schema_name');
        $this->assignString($data, 'schemaDescription', $config, 'schemaDescription', 'schema_description');
        $this->assignString($data, 'toolName', $config, 'toolName', 'tool_name');
        $this->assignString($data, 'toolDescription', $config, 'toolDescription', 'tool_description');
        $this->assignString($data, 'outputClass', $config, 'outputClass', 'output_class');
        $this->assignBool($data, 'defaultToStdClass', $config, 'defaultToStdClass', 'default_to_std_class');
        $this->assignString($data, 'deserializationErrorPromptClass', $config, 'deserializationErrorPromptClass', 'deserialization_error_prompt_class');
        $this->assignBool($data, 'throwOnTransformationFailure', $config, 'throwOnTransformationFailure', 'throw_on_transformation_failure');
        $this->assignArray($data, 'chatStructure', $config, 'chatStructure', 'chat_structure');
        $this->assignString($data, 'responseCachePolicy', $config, 'responseCachePolicy', 'response_cache_policy');
        $this->assignInt($data, 'streamMaterializationInterval', $config, 'streamMaterializationInterval', 'stream_materialization_interval');

        return $data;
    }

    /** @return array<string, mixed> */
    private function httpClientData(): array
    {
        $config = $this->httpRoot();

        return [
            'driver' => $this->nonEmptyString($this->stringValue($config, 'driver'), 'symfony'),
            'connectTimeout' => $this->intValue($config, 30, 'connectTimeout', 'connect_timeout'),
            'requestTimeout' => $this->intValue($config, 120, 'requestTimeout', 'request_timeout', 'timeout'),
            'idleTimeout' => $this->intValue($config, -1, 'idleTimeout', 'idle_timeout'),
            'streamChunkSize' => $this->intValue($config, 256, 'streamChunkSize', 'stream_chunk_size'),
            'streamHeaderTimeout' => $this->intValue($config, 5, 'streamHeaderTimeout', 'stream_header_timeout'),
            'failOnError' => $this->boolValue($config, false, 'failOnError', 'fail_on_error'),
        ];
    }

    /** @return array<string, mixed> */
    private function llmMetadata(array $config): array
    {
        return $this->mergeMetadata(
            $this->arrayValue($config, 'metadata'),
            [
                'organization' => $this->stringValue($config, 'organization'),
                'project' => $this->stringValue($config, 'project'),
                'resourceName' => $this->stringValue($config, 'resourceName', 'resource_name'),
                'deploymentId' => $this->stringValue($config, 'deploymentId', 'deployment_id'),
                'apiVersion' => $this->stringValue($config, 'apiVersion', 'api_version'),
                'beta' => $this->stringValue($config, 'beta'),
                'client_name' => $this->stringValue($config, 'clientName', 'client_name'),
                'region' => $this->stringValue($config, 'region'),
                'guardrailId' => $this->stringValue($config, 'guardrailId', 'guardrail_id'),
                'guardrailVersion' => $this->stringValue($config, 'guardrailVersion', 'guardrail_version'),
                'openResponsesVersion' => $this->stringValue($config, 'openResponsesVersion', 'open_responses_version'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function embeddingsMetadata(array $config): array
    {
        return $this->mergeMetadata(
            $this->arrayValue($config, 'metadata'),
            [
                'organization' => $this->stringValue($config, 'organization'),
                'project' => $this->stringValue($config, 'project'),
                'resourceName' => $this->stringValue($config, 'resourceName', 'resource_name'),
                'deploymentId' => $this->stringValue($config, 'deploymentId', 'deployment_id'),
                'apiVersion' => $this->stringValue($config, 'apiVersion', 'api_version'),
                'region' => $this->stringValue($config, 'region'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function llmOptions(array $config): array
    {
        $extra = array_diff_key($config, array_flip(self::LLM_RESERVED_KEYS));
        $options = $this->arrayValue($config, 'options');

        return match (true) {
            $options === [] => $extra,
            default => array_merge($extra, $options),
        };
    }

    /** @param array<string, mixed> $explicit */
    /** @param array<string, mixed> $derived */
    /** @return array<string, mixed> */
    private function mergeMetadata(array $explicit, array $derived): array
    {
        $filtered = [];

        foreach ($derived as $key => $value) {
            if ($value === '') {
                continue;
            }

            $filtered[$key] = $value;
        }

        return array_merge($filtered, $explicit);
    }

    /** @return array<string, mixed> */
    private function connectionsRoot(): array
    {
        return $this->arrayValue($this->config, 'connections');
    }

    /** @return array<string, mixed> */
    private function embeddingsRoot(): array
    {
        return $this->arrayValue($this->config, 'embeddings');
    }

    /** @return array<string, mixed> */
    private function extractionRoot(): array
    {
        return $this->arrayValue($this->config, 'extraction');
    }

    /** @return array<string, mixed> */
    private function httpRoot(): array
    {
        return $this->arrayValue($this->config, 'http');
    }

    /** @param array<string, mixed> $root */
    /** @return array<string, array<string, mixed>> */
    private function rawConnectionEntries(array $root): array
    {
        $entries = $this->nestedConnectionEntries($root);
        if ($entries !== []) {
            return $entries;
        }

        $connections = [];

        foreach ($root as $name => $config) {
            if (in_array((string) $name, self::CONNECTION_META_KEYS, true)) {
                continue;
            }

            if (! is_array($config)) {
                continue;
            }

            $connections[(string) $name] = $config;
        }

        return $connections;
    }

    /** @param array<string, mixed> $root */
    /** @return array<string, array<string, mixed>> */
    private function nestedConnectionEntries(array $root): array
    {
        $items = $this->arrayValue($root, 'items');
        if ($items !== []) {
            return $this->filterConnectionEntries($items);
        }

        $connections = $this->arrayValue($root, 'connections');
        if ($connections !== []) {
            return $this->filterConnectionEntries($connections);
        }

        return [];
    }

    /** @param array<string, mixed> $entries */
    /** @return array<string, array<string, mixed>> */
    private function filterConnectionEntries(array $entries): array
    {
        $filtered = [];

        foreach ($entries as $name => $config) {
            if (! is_array($config)) {
                continue;
            }

            $filtered[(string) $name] = $config;
        }

        return $filtered;
    }

    /** @param array<string, array<string, mixed>> $connections */
    private function firstConnectionName(array $connections, string $fallback): string
    {
        $first = array_key_first($connections);

        return match (true) {
            is_string($first) && $first !== '' => $first,
            default => $fallback,
        };
    }

    private function defaultApiUrl(string $driver): string
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

    private function defaultLlmEndpoint(string $driver, string $model): string
    {
        return match ($driver) {
            'anthropic' => '/messages',
            'gemini' => "/models/{$model}:generateContent",
            default => '/chat/completions',
        };
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim($path, '.');

        if (str_contains($normalized, '.presets.')) {
            return str_replace('.presets.', '.connections.', $normalized);
        }

        if (str_ends_with($normalized, '.presets')) {
            return str_replace('.presets', '.connections', $normalized);
        }

        if (str_ends_with($normalized, '.defaultPreset')) {
            return str_replace('.defaultPreset', '.default', $normalized);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $config */
    private function normalizeOutputMode(array $config): string
    {
        $value = $this->stringValue($config, 'outputMode', 'output_mode');

        return match ($value) {
            'tools' => 'tool_call',
            default => $value,
        };
    }

    /** @param array<string, mixed> $data */
    private function readPath(array $data, string $path, object $sentinel): mixed
    {
        if ($path === '') {
            return $data;
        }

        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value)) {
                return $sentinel;
            }

            if (! array_key_exists($segment, $value)) {
                return $sentinel;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function stringValue(array $data, string ...$keys): string
    {
        $value = $this->firstDefinedValue($data, $keys);

        return match (true) {
            is_string($value) => $value,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /** @param array<string, mixed> $data */
    private function intValue(array $data, int $default, string ...$keys): int
    {
        $value = $this->firstDefinedValue($data, $keys);

        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            is_float($value) => (int) $value,
            default => $default,
        };
    }

    /** @param array<string, mixed> $data */
    private function boolValue(array $data, bool $default, string ...$keys): bool
    {
        $value = $this->firstDefinedValue($data, $keys);

        if (is_bool($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return match (true) {
            $parsed === null => $default,
            default => $parsed,
        };
    }

    /** @param array<string, mixed> $data */
    /** @return array<string, mixed> */
    private function arrayValue(array $data, string ...$keys): array
    {
        $value = $this->firstDefinedValue($data, $keys);

        return match (true) {
            is_array($value) => $value,
            default => [],
        };
    }

    /** @param array<string, mixed> $data */
    /** @param array<int|string, string> $keys */
    private function firstDefinedValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            return $data[$key];
        }

        return null;
    }

    private function nonEmptyString(string ...$values): string
    {
        foreach ($values as $value) {
            if ($value === '') {
                continue;
            }

            return $value;
        }

        return '';
    }

    /** @param array<string, mixed> $data */
    private function assignString(array &$target, string $targetKey, array $data, string ...$sourceKeys): void
    {
        $value = $this->stringValue($data, ...$sourceKeys);
        if ($value === '') {
            return;
        }

        $target[$targetKey] = $value;
    }

    /** @param array<string, mixed> $data */
    private function assignInt(array &$target, string $targetKey, array $data, string ...$sourceKeys): void
    {
        $value = $this->firstDefinedValue($data, $sourceKeys);
        if ($value === null) {
            return;
        }

        $target[$targetKey] = $this->intValue($data, 0, ...$sourceKeys);
    }

    /** @param array<string, mixed> $data */
    private function assignBool(array &$target, string $targetKey, array $data, string ...$sourceKeys): void
    {
        $value = $this->firstDefinedValue($data, $sourceKeys);
        if ($value === null) {
            return;
        }

        $target[$targetKey] = $this->boolValue($data, false, ...$sourceKeys);
    }

    /** @param array<string, mixed> $data */
    private function assignArray(array &$target, string $targetKey, array $data, string ...$sourceKeys): void
    {
        $value = $this->arrayValue($data, ...$sourceKeys);
        if ($value === []) {
            return;
        }

        $target[$targetKey] = $value;
    }
}
