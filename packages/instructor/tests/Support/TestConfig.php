<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Support;

use Cognesy\Config\Config;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use InvalidArgumentException;

final class TestConfig
{
    public static function llmPreset(string $name): LLMConfig
    {
        return LLMConfig::fromArray(self::llmPresetData($name));
    }

    public static function llmDefaultProvider(): string
    {
        $data = self::llmConfigData();
        $defaultPreset = $data['defaultPreset'] ?? 'openai';

        return self::llmProviderForPreset($defaultPreset);
    }

    public static function llmProviderForPreset(string $name): string
    {
        $preset = self::llmPresetData($name);

        return match (true) {
            isset($preset['driver']) && is_string($preset['driver']) => $preset['driver'],
            isset($preset['providerType']) && is_string($preset['providerType']) => $preset['providerType'],
            default => throw new InvalidArgumentException("Preset '{$name}' does not define a provider"),
        };
    }

    /** @return array<string, mixed> */
    public static function llmPresetData(string $name): array
    {
        $data = self::llmConfigData();
        $preset = $data['presets'][$name] ?? null;

        return match (true) {
            is_array($preset) => $preset,
            default => throw new InvalidArgumentException("Unknown test LLM preset: {$name}"),
        };
    }

    /** @return array<string, mixed> */
    private static function llmConfigData(): array
    {
        return Config::fromPaths(__DIR__.'/../Fixtures/Setup/config')
            ->load('llm.yaml')
            ->toArray();
    }
}
