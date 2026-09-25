<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Config;

use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Support\Config\PresetConfigLoader;
use Cognesy\Polyglot\Support\Redaction\SensitiveDataRedactor;
use InvalidArgumentException;
use Throwable;

final class EmbeddingsConfig
{
    public const CONFIG_GROUP = 'embed';

    public static function group(): string
    {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        #[\SensitiveParameter]
        public string $apiUrl = '',
        #[\SensitiveParameter]
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
        #[\SensitiveParameter]
        public array $metadata = [],
        public string $driver = 'openai',
    ) {}

    private const PRESET_PATHS = [
        'config/embed/presets',
        'packages/polyglot/resources/config/embed/presets',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/embed/presets',
        'vendor/cognesy/instructor-polyglot/resources/config/embed/presets',
        __DIR__.'/../../../resources/config/embed/presets',
    ];

    public static function fromDefaultPreset(): self
    {
        $preset = PresetConfigLoader::defaultName('embeddings', self::PRESET_PATHS);

        return self::fromPreset($preset);
    }

    public static function fromPreset(string $preset, ?string $basePath = null): self
    {
        $paths = $basePath !== null ? [$basePath] : self::PRESET_PATHS;
        $data = PresetConfigLoader::load($preset, $paths);

        return self::fromArray($data);
    }

    public static function fromArray(#[\SensitiveParameter] array $config): EmbeddingsConfig
    {
        try {
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $fields = SensitiveDataRedactor::summarizeFieldTypes($config);
            throw new InvalidArgumentException(
                message: "Invalid configuration for EmbeddingsConfig: {$e->getMessage()}\nFields: {$fields}",
                previous: $e,
            );
        }

        return $instance;
    }

    public static function fromDsn(#[\SensitiveParameter] string $dsn): self
    {
        return self::fromArray(Dsn::fromString($dsn)->toArray());
    }

    public function withOverrides(#[\SensitiveParameter] array $values): self
    {
        $config = array_merge($this->toArray(), $values);

        return self::fromArray($config);
    }

    public function toArray(): array
    {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'model' => $this->model,
            'metadata' => $this->metadata,
            'driver' => $this->driver,
        ];
    }
}
