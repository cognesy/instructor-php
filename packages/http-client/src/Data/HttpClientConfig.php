<?php
namespace Cognesy\Http\Data;

/**
 * Class HttpClientConfig
 *
 * Configuration class for HTTP clients.
 */
final class HttpClientConfig
{
    /**
     * Constructor for HttpClientConfig.
     *
     * @param string $driver The driver name of HTTP client.
     * @param int $connectTimeout Connection timeout in seconds.
     * @param int $requestTimeout Request timeout in seconds.
     * @param int $idleTimeout Idle timeout in seconds.
     * @param int $maxConcurrent Maximum number of concurrent connections.
     * @param int $poolTimeout Pool timeout in seconds.
     * @param bool $failOnError Whether to fail on error.
     */
    public function __construct(
        public string $driver = 'guzzle',
        public int    $connectTimeout = 3,
        public int    $requestTimeout = 30,
        public int    $idleTimeout = -1,
        // Concurrency-related properties
        public int    $maxConcurrent = 5,
        public int    $poolTimeout = 120,
        public bool   $failOnError = false,
    ) {}

    /**
     * Creates a new HttpClientConfig instance from an associative array.
     *
     * @param array $config An associative array containing configuration options.
     * @return HttpClientConfig The corresponding HttpClientConfig instance based on the provided array.
     */
    public static function fromArray(array $config) : HttpClientConfig {
        return new HttpClientConfig(
            driver: $config['httpClientDriver'] ?? 'guzzle',
            connectTimeout: $config['connectTimeout'] ?? 3,
            requestTimeout: $config['requestTimeout'] ?? 30,
            idleTimeout: $config['idleTimeout'] ?? -1,
            maxConcurrent: $config['maxConcurrent'] ?? 5,
            poolTimeout: $config['poolTimeout'] ?? 120,
            failOnError: $config['failOnError'] ?? false,
        );
    }

    public function toArray() : array {
        return [
            'httpClientDriver' => $this->driver,
            'connectTimeout' => $this->connectTimeout,
            'requestTimeout' => $this->requestTimeout,
            'idleTimeout' => $this->idleTimeout,
            'maxConcurrent' => $this->maxConcurrent,
            'poolTimeout' => $this->poolTimeout,
            'failOnError' => $this->failOnError,
        ];
    }
}
