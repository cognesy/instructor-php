<?php
namespace Cognesy\Http\Data;

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
        public string $httpClientType = 'guzzle',
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
     * @param string $preset The client configuration name to load.
     * @return HttpClientConfig The HTTP client configuration object.
     * @throws InvalidArgumentException If the specified client is unknown.
     */
    public static function load(string $preset) : HttpClientConfig {
        if (!Settings::has('http', "clientPresets.$preset")) {
            throw new InvalidArgumentException("Unknown client preset: $preset");
        }
        return new HttpClientConfig(
            httpClientType: Settings::get('http', "clientPresets.$preset.httpClientType", 'guzzle'),
            connectTimeout: Settings::get(group: "http", key: "clientPresets.$preset.connectTimeout", default: 30),
            requestTimeout: Settings::get("http", "clientPresets.$preset.requestTimeout", 3),
            idleTimeout: Settings::get(group: "http", key: "clientPresets.$preset.idleTimeout", default: 0),
            maxConcurrent: Settings::get("http", "clientPresets.$preset.maxConcurrent", 5),
            poolTimeout: Settings::get("http", "clientPresets.$preset.poolTimeout", 120),
            failOnError: Settings::get("http", "clientPresets.$preset.failOnError", false),
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
            httpClientType: $config['httpClientType'] ?? 'guzzle',
            connectTimeout: $config['connectTimeout'] ?? 3,
            requestTimeout: $config['requestTimeout'] ?? 30,
            idleTimeout: $config['idleTimeout'] ?? -1,
            maxConcurrent: $config['maxConcurrent'] ?? 5,
            poolTimeout: $config['poolTimeout'] ?? 120,
            failOnError: $config['failOnError'] ?? false,
        );
    }
}
