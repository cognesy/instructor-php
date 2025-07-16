<?php declare(strict_types=1);

namespace Cognesy\Http\Config;

use Cognesy\Config\Dsn;
use Cognesy\Config\Exceptions\ConfigurationException;

/**
 * Class HttpClientConfig
 *
 * Configuration class for HTTP clients.
 */
final class HttpClientConfig
{
    public const CONFIG_GROUP = 'http';

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    /**
     * Constructor for HttpClientConfig.
     *
     * @param string $driver The driver name of HTTP client.
     * @param int $connectTimeout Max time to connect in seconds.
     * @param int $requestTimeout Max total request execution time in seconds.
     * @param int $idleTimeout Idle timeout in seconds (if supported by the driver).
     * @param int $maxConcurrent Maximum number of concurrent connections.
     * @param int $poolTimeout Pool timeout in seconds.
     * @param bool $failOnError Whether to fail on error.
     */
    public function __construct(
        public readonly string $driver = '',
        public readonly int    $connectTimeout = 3,
        public readonly int    $requestTimeout = 30,
        public readonly int    $idleTimeout = -1,
        public readonly int    $streamChunkSize = 256,
        // Concurrency-related properties
        public readonly int    $maxConcurrent = 5,
        public readonly int    $poolTimeout = 120,
        public readonly bool   $failOnError = false,
    ) {}

    public static function fromDsn(string $dsn) : HttpClientConfig {
        $data = Dsn::fromString($dsn)->toArray();
        unset($data['preset']);
        return self::fromArray($data);
    }

    /**
     * Creates a new HttpClientConfig instance from an associative array.
     *
     * @param array $config An associative array containing configuration options.
     * @return HttpClientConfig The corresponding HttpClientConfig instance based on the provided array.
     */
    public static function fromArray(array $config) : HttpClientConfig {
        try {
            $instance = new self(...$config);
        } catch (\InvalidArgumentException $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Failed to create HttpClientConfig from array:\n$data\nError: {$e->getMessage()}",
                previous: $e
            );
        }
        return $instance;
    }

    public function withOverrides(array $overrides) : self {
        $config = array_merge($this->toArray(), $overrides);
        return self::fromArray($config);
    }

    public function toArray() : array {
        return [
            'driver' => $this->driver,
            'connectTimeout' => $this->connectTimeout,
            'requestTimeout' => $this->requestTimeout,
            'idleTimeout' => $this->idleTimeout,
            'streamChunkSize' => $this->streamChunkSize,
            'maxConcurrent' => $this->maxConcurrent,
            'poolTimeout' => $this->poolTimeout,
            'failOnError' => $this->failOnError,
        ];
    }
}
