<?php

declare(strict_types=1);

namespace Cognesy\Tell\Discovery;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Data\DriverCapabilities;
use Cognesy\Tell\Configuration\TellPaths;
use InvalidArgumentException;

/** Read-only view over Polyglot presets and bundled driver specifications. */
final readonly class TellProviderCatalogue
{
    public function __construct(private TellPaths $paths) {}

    /** @return array{connections: list<array<string,mixed>>, errors: list<array<string,string>>} */
    public function connections(string $project): array {
        $connections = [];
        $errors = [];
        foreach ([
            'bundled' => null,
            'user' => $this->paths->connections,
            'project' => rtrim($project, '/\\') . '/config/llm/presets',
        ] as $source => $directory) {
            foreach (LLMConfig::presetNames($directory) as $name) {
                try {
                    $config = LLMConfig::fromPreset($name, $directory);
                } catch (InvalidArgumentException $error) {
                    $errors[] = ['connection' => $name, 'source' => $source, 'error' => $error->getMessage()];

                    continue;
                }
                $connections[$name] = $this->entry($name, $source, $config);
            }
        }
        ksort($connections, SORT_STRING);
        usort($errors, static fn (array $left, array $right): int => [$left['connection'], $left['source']] <=> [$right['connection'], $right['source']]);

        return ['connections' => array_values($connections), 'errors' => $errors];
    }

    /** @return list<array<string,mixed>> */
    public function models(string $project, ?string $selector = null): array {
        $rows = $this->connections($project)['connections'];
        if ($selector === null || $selector === '') {
            return $rows;
        }
        $matches = array_values(array_filter($rows, static fn (array $row): bool => $row['connection'] === $selector || $row['provider'] === $selector));
        if ($matches === []) {
            throw new InvalidArgumentException("Unknown provider or connection '{$selector}'.");
        }

        return $matches;
    }

    /** @return array<string,mixed> */
    public function resolve(string $project, string $connection, string $model = ''): array {
        $rows = $this->models($project, $connection);
        $connectionRow = current(array_filter($rows, static fn (array $row): bool => $row['connection'] === $connection));
        if (!is_array($connectionRow)) {
            throw new InvalidArgumentException("Unknown connection '{$connection}'.");
        }
        $effectiveModel = $model !== '' ? $model : $connectionRow['defaultModel'];

        return [
            ...$connectionRow,
            'model' => $effectiveModel,
            'modelSource' => $model !== '' ? 'override' : 'preset',
        ];
    }

    /** @return array<string,mixed> */
    private function entry(string $name, string $source, LLMConfig $config): array {
        $capabilities = BundledInferenceDrivers::capabilities($config->driver, $config->model);

        return [
            'connection' => $name,
            'provider' => $config->driver,
            'source' => $source,
            'defaultModel' => $config->model,
            'availableModels' => $config->model === '' ? [] : [$config->model],
            'contextCapacity' => $config->contextLength,
            'maxOutputTokens' => $config->maxOutputLength,
            'capabilities' => $this->capabilities($capabilities),
            'unknown' => [
                'vision' => 'not declared by Polyglot driver metadata',
                'thinking' => 'not declared by Polyglot driver metadata',
                'modelCatalogue' => 'the preset declares its default model only',
            ],
            'provenance' => [
                'connection' => $source . ' preset',
                'model' => $source . ' preset',
                'contextCapacity' => $source . ' preset',
                'capabilities' => $capabilities === null ? 'unknown driver' : 'bundled driver specification',
            ],
        ];
    }

    /** @return array<string,bool|null> */
    private function capabilities(?DriverCapabilities $capabilities): array {
        return [
            'streaming' => $capabilities?->supportsStreaming(),
            'tools' => $capabilities?->supportsToolCalling(),
            'toolChoice' => $capabilities?->supportsToolChoice(),
            'jsonObject' => $capabilities?->supportsResponseFormatJsonObject(),
            'jsonSchema' => $capabilities?->supportsResponseFormatJsonSchema(),
            'responseFormatWithTools' => $capabilities?->supportsResponseFormatWithTools(),
            'reasoningEffort' => $capabilities?->reasoning()->supportsEffort(),
        ];
    }
}
