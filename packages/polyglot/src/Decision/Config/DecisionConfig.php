<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Config;

use Cognesy\Config\Dsn;
use Cognesy\Config\EnvTemplate;
use Cognesy\Polyglot\Support\Config\PresetConfigLoader;
use Cognesy\Polyglot\Support\Redaction\SensitiveDataRedactor;
use InvalidArgumentException;
use SensitiveParameter;
use Throwable;

final class DecisionConfig
{
    public const string CONFIG_GROUP = 'sdm';

    /** @var list<string> */
    private const array FIELDS = ['driver', 'apiUrl', 'apiKey', 'endpoint', 'model'];

    /** @var list<string> */
    private const array PRESET_PATHS = [
        'config/sdm/presets',
        'packages/polyglot/resources/config/sdm/presets',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/sdm/presets',
        'vendor/cognesy/instructor-polyglot/resources/config/sdm/presets',
        __DIR__.'/../../../resources/config/sdm/presets',
    ];

    public function __construct(
        public string $driver = '',
        #[SensitiveParameter]
        public string $apiUrl = '',
        #[SensitiveParameter]
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
    ) {}

    public static function group(): string
    {
        return self::CONFIG_GROUP;
    }

    public static function fromDefaults(?EnvTemplate $template = null): self
    {
        $preset = PresetConfigLoader::defaultName('SDM', self::PRESET_PATHS, $template);

        return self::fromPreset($preset, template: $template);
    }

    public static function fromPreset(
        string $preset,
        ?string $basePath = null,
        ?EnvTemplate $template = null,
    ): self {
        $paths = $basePath === null ? self::PRESET_PATHS : [$basePath];

        return self::fromArray(PresetConfigLoader::load($preset, $paths, $template));
    }

    /** @return list<string> */
    public static function presetNames(?string $basePath = null): array
    {
        return PresetConfigLoader::names($basePath === null ? self::PRESET_PATHS : [$basePath]);
    }

    public static function fromArray(#[SensitiveParameter] array $config): self
    {
        $unknown = array_values(array_diff(array_keys($config), self::FIELDS));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown DecisionConfig fields: '.implode(', ', $unknown));
        }

        try {
            return new self(...$config);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                message: 'Invalid configuration for DecisionConfig: '.$exception->getMessage()
                    .' Fields: '.SensitiveDataRedactor::summarizeFieldTypes($config),
                previous: $exception,
            );
        }
    }

    public static function fromDsn(#[SensitiveParameter] string $dsn): self
    {
        if (! Dsn::isDsn($dsn)) {
            throw new InvalidArgumentException('Invalid DecisionConfig DSN: expected comma-separated key=value pairs.');
        }

        return self::fromArray(Dsn::fromString($dsn)->toArray());
    }

    public function withOverrides(#[SensitiveParameter] array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    public function assertUsable(?string $modelOverride = null): void
    {
        self::assertPresent($this->driver, 'driver');
        self::assertPresent($this->apiUrl, 'apiUrl');
        self::assertPresent($this->apiKey, 'apiKey');
        self::assertPresent($this->endpoint, 'endpoint');
        self::assertPresent($modelOverride ?? $this->model, 'model');

        $scheme = parse_url($this->apiUrl, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true) || filter_var($this->apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Decision configuration field 'apiUrl' must be an HTTP(S) URL.");
        }
        if (! str_starts_with($this->endpoint, '/')) {
            throw new InvalidArgumentException("Decision configuration field 'endpoint' must start with '/'.");
        }
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'model' => $this->model,
        ];
    }

    public function toRedactedArray(): array
    {
        return SensitiveDataRedactor::redactValues($this->toArray());
    }

    private static function assertPresent(#[SensitiveParameter] string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("Decision configuration field '{$field}' is missing or empty.");
        }
    }
}
