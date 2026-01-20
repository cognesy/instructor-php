<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Definitions;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class AgentDefinitionParser
{
    private const int SUPPORTED_VERSION = 1;
    /** @var array<int, string> */
    private const array ROOT_KEYS = [
        'version',
        'id',
        'name',
        'description',
        'system_prompt',
        'blueprint',
        'blueprint_class',
        'llm',
        'execution',
        'tools',
        'capabilities',
        'metadata',
    ];
    /** @var array<int, string> */
    private const array LLM_KEYS = [
        'preset',
        'model',
        'temperature',
        'max_output_tokens',
    ];
    /** @var array<int, string> */
    private const array EXECUTION_KEYS = [
        'max_steps',
        'max_tokens',
        'timeout_sec',
        'error_policy',
        'error_policy_max_retries',
    ];
    /** @var array<int, string> */
    private const array TOOLS_KEYS = [
        'allow',
        'deny',
    ];

    public function parseYamlFile(string $path): AgentDefinition
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read agent definition file: {$path}");
        }

        return $this->parseYamlString($content);
    }

    public function parseYamlString(string $yaml): AgentDefinition
    {
        $data = $this->parseYaml($yaml);

        return $this->parseArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function parseArray(array $data): AgentDefinition
    {
        $this->assertNoUnknownKeys($data, self::ROOT_KEYS, 'agent definition');

        $version = $this->requireInt($data, 'version', 'agent definition');
        if ($version !== self::SUPPORTED_VERSION) {
            throw new InvalidArgumentException(
                "Unsupported agent definition version '{$version}'"
            );
        }

        $id = $this->requireString($data, 'id', 'agent definition');
        $name = $this->requireString($data, 'name', 'agent definition');
        $description = $this->requireString($data, 'description', 'agent definition');
        $systemPrompt = $this->requireString($data, 'system_prompt', 'agent definition');
        $systemPrompt = trim($systemPrompt);
        if ($systemPrompt === '') {
            throw new InvalidArgumentException(
                "Invalid 'system_prompt' field in agent definition: cannot be empty"
            );
        }

        $blueprint = $this->optionalString($data, 'blueprint', 'agent definition');
        $blueprintClass = $this->optionalString($data, 'blueprint_class', 'agent definition');
        if ($blueprint !== null && $blueprintClass !== null) {
            throw new InvalidArgumentException(
                "Provide only one of 'blueprint' or 'blueprint_class'"
            );
        }

        $llmData = $this->requireArray($data, 'llm', 'agent definition');
        $this->assertNoUnknownKeys($llmData, self::LLM_KEYS, 'llm');
        $preset = $this->requireString($llmData, 'preset', 'llm');
        $model = $this->optionalString($llmData, 'model', 'llm');
        $temperature = $this->optionalFloat($llmData, 'temperature', 'llm');
        $maxOutputTokens = $this->optionalInt($llmData, 'max_output_tokens', 'llm');
        $llm = new AgentDefinitionLlm(
            preset: $preset,
            model: $model,
            temperature: $temperature,
            maxOutputTokens: $maxOutputTokens,
        );

        $execution = $this->parseExecution($data['execution'] ?? null);
        $tools = $this->parseTools($data['tools'] ?? null);

        $capabilities = $this->parseStringList($data['capabilities'] ?? null, 'capabilities');
        $capabilities = $capabilities ?? [];

        $metadata = $data['metadata'] ?? [];
        if (!is_array($metadata)) {
            throw new InvalidArgumentException(
                "Invalid 'metadata' field in agent definition: must be a map"
            );
        }

        return new AgentDefinition(
            version: $version,
            id: $id,
            name: $name,
            description: $description,
            systemPrompt: $systemPrompt,
            blueprint: $blueprint,
            blueprintClass: $blueprintClass,
            llm: $llm,
            execution: $execution,
            tools: $tools,
            capabilities: $capabilities,
            metadata: $metadata,
        );
    }

    // INTERNAL /////////////////////////////////////////////////////

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $yaml): array
    {
        try {
            $parsed = Yaml::parse(
                trim($yaml),
                Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Invalid YAML: {$e->getMessage()}",
                previous: $e
            );
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException(
                "Invalid YAML: agent definition must be a map"
            );
        }

        /** @var array<string, mixed> $parsed */
        return $parsed;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $allowedKeys
     */
    private function assertNoUnknownKeys(array $data, array $allowedKeys, string $context): void
    {
        $unknown = array_diff(array_keys($data), $allowedKeys);
        if ($unknown === []) {
            return;
        }

        sort($unknown);

        throw new InvalidArgumentException(
            "Unknown keys in {$context}: " . implode(', ', $unknown)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key, string $context): string
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidArgumentException("Missing '{$key}' field in {$context}");
        }

        $value = $data[$key];
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: must be string"
            );
        }

        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: cannot be empty"
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $key, string $context): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: must be string"
            );
        }

        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: cannot be empty"
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireInt(array $data, string $key, string $context): int
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidArgumentException("Missing '{$key}' field in {$context}");
        }

        $value = $data[$key];
        if (!is_int($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: must be integer"
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalInt(array $data, string $key, string $context): ?int
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];
        if (!is_int($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: must be integer"
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalFloat(array $data, string $key, string $context): ?float
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];
        if (!is_float($value) && !is_int($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: must be number"
            );
        }

        return (float) $value;
    }

    private function parseExecution(mixed $execution): AgentDefinitionExecution
    {
        if ($execution === null) {
            return new AgentDefinitionExecution();
        }

        if (!is_array($execution)) {
            throw new InvalidArgumentException(
                "Invalid 'execution' field in agent definition: must be a map"
            );
        }

        $this->assertNoUnknownKeys($execution, self::EXECUTION_KEYS, 'execution');

        return new AgentDefinitionExecution(
            maxSteps: $this->optionalInt($execution, 'max_steps', 'execution'),
            maxTokens: $this->optionalInt($execution, 'max_tokens', 'execution'),
            timeoutSec: $this->optionalInt($execution, 'timeout_sec', 'execution'),
            errorPolicy: $this->optionalString($execution, 'error_policy', 'execution'),
            errorPolicyMaxRetries: $this->optionalInt($execution, 'error_policy_max_retries', 'execution'),
        );
    }

    private function parseTools(mixed $tools): AgentDefinitionTools
    {
        if ($tools === null) {
            return new AgentDefinitionTools();
        }

        if (!is_array($tools)) {
            throw new InvalidArgumentException(
                "Invalid 'tools' field in agent definition: must be a map"
            );
        }

        $this->assertNoUnknownKeys($tools, self::TOOLS_KEYS, 'tools');

        return new AgentDefinitionTools(
            allow: $this->parseStringList($tools['allow'] ?? null, 'tools.allow'),
            deny: $this->parseStringList($tools['deny'] ?? null, 'tools.deny'),
        );
    }

    /**
     * @return array<int, string>|null
     */
    private function parseStringList(mixed $value, string $context): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$context}' field in agent definition: must be a list of strings"
            );
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(
                    "Invalid '{$context}' field in agent definition: must be a list of strings"
                );
            }
            $item = trim($item);
            if ($item === '') {
                throw new InvalidArgumentException(
                    "Invalid '{$context}' field in agent definition: values cannot be empty"
                );
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireArray(array $data, string $key, string $context): array
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidArgumentException("Missing '{$key}' field in {$context}");
        }

        $value = $data[$key];
        if (!is_array($value)) {
            throw new InvalidArgumentException(
                "Invalid '{$key}' field in {$context}: must be a map"
            );
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
