<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Config;

use Cognesy\Config\Exceptions\ConfigurationException;
use Throwable;

final class EmbeddingsConfig
{
    public const CONFIG_GROUP = 'embed';

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

    public static function fromArray(array $config) : EmbeddingsConfig {
        $normalized = self::normalizeConfigArray($config);

        try {
            $instance = new self(...$normalized);
        } catch (Throwable $e) {
            $data = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Invalid configuration for EmbeddingsConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
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
}
