<?php

namespace Cognesy\Http\Data;

use Cognesy\Http\Enums\HttpClientType;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

/**
 * Class HttpClientConfig
 *
 * Configuration class for HTTP clients.
 */
class HttpClientConfig
{

    /**
     * Constructor for HttpClientConfig.
     *
     * @param string $httpClientType The type of HTTP client.
     * @param int $connectTimeout Connection timeout in seconds.
     * @param int $requestTimeout Request timeout in seconds.
     * @param int $idleTimeout Idle timeout in seconds.
     * @param int $maxConcurrent Maximum number of concurrent connections.
     * @param int $poolTimeout Pool timeout in seconds.
     * @param bool $failOnError Whether to fail on error.
     */
    public function __construct(
        public string $httpClientType = HttpClientType::Guzzle->value,
        public int $connectTimeout = 3,
        public int $requestTimeout = 30,
        public int $idleTimeout = -1,
        // Concurrency-related properties
        public int $maxConcurrent = 5,
        public int $poolTimeout = 120,
        public bool $failOnError = false,
    ) {}

    /**
     * Loads the HTTP client configuration for the specified client configuration
     *
     * @param string $client The client configuration name to load.
     * @return HttpClientConfig The HTTP client configuration object.
     * @throws InvalidArgumentException If the specified client is unknown.
     */
    public static function load(string $client) : HttpClientConfig {
        if (!Settings::has('http', "clients.$client")) {
            throw new InvalidArgumentException("Unknown client: $client");
        }
        return new HttpClientConfig(
            httpClientType: Settings::get('http', "clients.$client.httpClientType", HttpClientType::Guzzle->value),
            connectTimeout: Settings::get(group: "http", key: "clients.$client.connectTimeout", default: 30),
            requestTimeout: Settings::get("http", "clients.$client.requestTimeout", 3),
            idleTimeout: Settings::get(group: "http", key: "clients.$client.idleTimeout", default: 0),
            maxConcurrent: Settings::get("http", "clients.$client.maxConcurrent", 5),
            poolTimeout: Settings::get("http", "clients.$client.poolTimeout", 120),
            failOnError: Settings::get("http", "clients.$client.failOnError", false),
        );
    }

    /**
     * Creates a new HttpClientConfig instance from an associative array.
     *
     * @param array $config An associative array containing configuration options.
     * @return HttpClientConfig The corresponding HttpClientConfig instance based on the provided array.
     */
    public static function fromArray(array $config) : HttpClientConfig {
        return new HttpClientConfig(
            httpClientType: $config['httpClientType'] ?? HttpClientType::Guzzle->value,
            connectTimeout: $config['connectTimeout'] ?? 3,
            requestTimeout: $config['requestTimeout'] ?? 30,
            idleTimeout: $config['idleTimeout'] ?? -1,
            maxConcurrent: $config['maxConcurrent'] ?? 5,
            poolTimeout: $config['poolTimeout'] ?? 120,
            failOnError: $config['failOnError'] ?? false,
        );
    }
}
