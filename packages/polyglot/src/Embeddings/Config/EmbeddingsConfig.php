<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Config;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use Cognesy\Config\Dsn;
use InvalidArgumentException;
use Throwable;

final class EmbeddingsConfig
{
    public const CONFIG_GROUP = 'embed';
    /** @var list<string> */
    private const INT_FIELDS = ['dimensions', 'maxInputs'];

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public string $endpoint = '',
        public string $model = '',
        public int    $dimensions = 0,
        public int    $maxInputs = 0,
        public array  $metadata = [],
        public string $driver = 'openai',
    ) {}

    private const PRESET_PATHS = [
        'config/embed/presets',
        'packages/polyglot/resources/config/embed/presets',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/embed/presets',
        'vendor/cognesy/instructor-polyglot/resources/config/embed/presets',
    ];

    public static function fromPreset(string $preset, ?string $basePath = null): self {
        $basePaths = $basePath !== null ? [$basePath] : self::PRESET_PATHS;
        $resolvedPaths = BasePath::resolveExisting(...$basePaths);
        if ($resolvedPaths === []) {
            throw new InvalidArgumentException("No preset directory found for '{$preset}'. Searched: " . implode(', ', $basePaths));
        }
        $data = Config::fromPaths(...$resolvedPaths)
            ->load("{$preset}.yaml")
            ->toArray();
        return self::fromArray($data);
    }

    public static function fromArray(array $config) : EmbeddingsConfig {
        $normalized = self::coerceScalarTypes(self::normalizeConfigArray($config));

        try {
            $instance = new self(...$normalized);
        } catch (Throwable $e) {
            $data = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new InvalidArgumentException(
                message: "Invalid configuration for EmbeddingsConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
    }

    public static function fromDsn(string $dsn): self {
        return self::fromArray(Dsn::fromString($dsn)->toArray());
    }

    public function withOverrides(array $values) : self {
        $config = array_merge($this->toArray(), $values);
        return self::fromArray($config);
    }

    public function toArray() : array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'model' => $this->model,
            'dimensions' => $this->dimensions,
            'maxInputs' => $this->maxInputs,
            'metadata' => $this->metadata,
            'driver' => $this->driver,
        ];
    }

    private static function normalizeConfigArray(array $config): array {
        if (array_key_exists('dimensions', $config)) {
            return $config;
        }

        if (!array_key_exists('defaultDimensions', $config)) {
            return $config;
        }

        $config['dimensions'] = $config['defaultDimensions'];
        unset($config['defaultDimensions']);

        return $config;
    }

    private static function coerceScalarTypes(array $config): array {
        $typed = $config;
        foreach (self::INT_FIELDS as $field) {
            if (!array_key_exists($field, $typed)) {
                continue;
            }
            $typed[$field] = self::toInt($field, $typed[$field]);
        }
        return $typed;
    }

    private static function toInt(string $field, mixed $value): int {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected integer, got %s', $field, get_debug_type($value)),
            ),
        };
    }
}
