<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Discovery\Polyglot;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Models\ModelProfile;
use Cognesy\Polyglot\Inference\Models\SupportStatus;
use Cognesy\Tell\Core\Contract\Discovery\CanCatalogueTellProviders;
use Cognesy\Tell\Core\Paths\TellPaths;
use InvalidArgumentException;
use Override;

/** Read-only join of connection presets and exact Polyglot model offerings. */
final readonly class PolyglotTellProviderCatalogue implements CanCatalogueTellProviders
{
    public function __construct(private TellPaths $paths) {}

    #[Override]
    public function catalog(string $project): ModelCatalog {
        $catalog = ModelCatalog::bundled();
        foreach ([$this->paths->models, rtrim($project, '/\\') . '/config/llm/models.json'] as $path) {
            if (is_file($path)) {
                $catalog = $catalog->overlay(ModelCatalog::fromFile($path));
            }
        }

        return $catalog;
    }

    /** @return array{connections: list<array<string,mixed>>, errors: list<array<string,string>>} */
    #[Override]
    public function connections(string $project): array {
        $catalog = $this->catalog($project);
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
                $connections[$name] = $this->connectionEntry($name, $source, $config, $catalog);
            }
        }
        ksort($connections, SORT_STRING);
        usort($errors, static fn (array $left, array $right): int => [$left['connection'], $left['source']] <=> [$right['connection'], $right['source']]);

        return ['connections' => array_values($connections), 'errors' => $errors];
    }

    /** @return list<array<string,mixed>> */
    #[Override]
    public function models(string $project, ?string $selector = null): array {
        $connections = $this->connections($project)['connections'];
        $providers = $this->selectedProviders($connections, $selector);
        $catalog = $this->catalog($project);
        $profiles = [];
        foreach ($catalog as $profile) {
            if (in_array($profile->key->driver, $providers, true)) {
                $profiles[$profile->key->lookupKey()] = $profile;
            }
        }
        foreach ($connections as $connection) {
            if (!in_array($connection['provider'], $providers, true)) {
                continue;
            }
            $profile = $catalog->find($connection['provider'], $connection['defaultModel']);
            $profiles[$profile->key->lookupKey()] = $profile;
        }
        ksort($profiles, SORT_STRING);

        return array_map(
            fn (ModelProfile $profile): array => $this->modelEntry($profile, $connections),
            array_values($profiles),
        );
    }

    /** @return array<string,mixed> */
    #[Override]
    public function resolve(string $project, string $connection, string $model = ''): array {
        $rows = $this->connections($project)['connections'];
        $connectionRow = current(array_filter($rows, static fn (array $row): bool => $row['connection'] === $connection));
        if (!is_array($connectionRow)) {
            throw new InvalidArgumentException("Unknown connection '{$connection}'.");
        }
        $effectiveModel = match ($model) {
            '' => $connectionRow['defaultModel'],
            default => $model,
        };
        $profile = $this->catalog($project)->find($connectionRow['provider'], $effectiveModel);

        return [
            ...$connectionRow,
            ...$this->profileFields($profile),
            'model' => $effectiveModel,
            'modelSource' => match ($model) {
                '' => 'preset',
                default => 'override',
            },
        ];
    }

    /** @return array<string,mixed> */
    private function connectionEntry(string $name, string $source, LLMConfig $config, ModelCatalog $catalog): array {
        $profile = $catalog->find($config->driver, $config->model);
        $availableModels = array_map(
            static fn (ModelProfile $model): string => $model->key->model,
            iterator_to_array($catalog->forDriver($config->driver)),
        );

        return [
            'connection' => $name,
            'provider' => $config->driver,
            'source' => $source,
            'defaultModel' => $config->model,
            'availableModels' => $availableModels,
            ...$this->profileFields($profile),
            'provenance' => [
                'connection' => $source . ' preset',
                'model' => $source . ' preset',
                'modelFacts' => $profile->source,
                'catalogVersion' => $profile->catalogVersion,
            ],
        ];
    }

    /** @param list<array<string,mixed>> $connections */
    private function modelEntry(ModelProfile $profile, array $connections): array {
        $providerConnections = array_values(array_filter(
            $connections,
            static fn (array $connection): bool => $connection['provider'] === $profile->key->driver,
        ));

        return [
            'provider' => $profile->key->driver,
            'model' => $profile->key->model,
            'connections' => array_column($providerConnections, 'connection'),
            'defaultFor' => array_values(array_column(array_filter(
                $providerConnections,
                static fn (array $connection): bool => $connection['defaultModel'] === $profile->key->model,
            ), 'connection')),
            ...$this->profileFields($profile),
            'provenance' => [
                'modelFacts' => $profile->source,
                'catalogVersion' => $profile->catalogVersion,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function profileFields(ModelProfile $profile): array {
        return [
            'status' => $profile->status->value,
            'contextCapacity' => $profile->limits->contextWindow,
            'maxOutputTokens' => $profile->limits->maxOutput,
            'modalities' => $profile->modalities->toArray(),
            'capabilities' => [
                'streaming' => $this->support($profile->capabilities->streaming),
                'tools' => $this->support($profile->capabilities->tools),
                'toolChoice' => $this->support($profile->capabilities->toolChoice),
                'jsonObject' => $this->support($profile->capabilities->jsonObject),
                'jsonSchema' => $this->support($profile->capabilities->jsonSchema),
                'responseFormatWithTools' => $this->support($profile->capabilities->responseFormatWithTools),
                'reasoningEffort' => $profile->capabilities->reasoning->supportsEffort(),
            ],
            'catalogSource' => $profile->source,
            'catalogVersion' => $profile->catalogVersion,
        ];
    }

    /** @param list<array<string,mixed>> $connections @return list<string> */
    private function selectedProviders(array $connections, ?string $selector): array {
        $all = array_values(array_unique(array_column($connections, 'provider')));
        sort($all, SORT_STRING);
        if ($selector === null || $selector === '') {
            return $all;
        }

        $matches = array_values(array_filter(
            $connections,
            static fn (array $row): bool => $row['connection'] === $selector || $row['provider'] === $selector,
        ));
        if ($matches === []) {
            throw new InvalidArgumentException("Unknown provider or connection '{$selector}'.");
        }

        return array_values(array_unique(array_column($matches, 'provider')));
    }

    private function support(SupportStatus $status): ?bool {
        return match ($status) {
            SupportStatus::Supported => true,
            SupportStatus::Unsupported => false,
            SupportStatus::Unknown => null,
        };
    }
}
