<?php

namespace Cognesy\Polyglot;

use Cognesy\Utils\Dsn\DSN;
use Cognesy\Utils\Settings;
use InvalidArgumentException;

class ProviderConfig
{
    public function __construct(
        public string $apiUrl = '',
        public string $apiKey = '',
        public array  $queryParams = [],
        public string $httpClient = '',
        public array  $metadata = [],
    ) {}

    public static function load(string $preset): static {
        if (!Settings::has('provider', "presets.$preset")) {
            throw new InvalidArgumentException("Unknown connection preset: $preset");
        }
        return new static(
            apiUrl: Settings::get('provider', "presets.$preset.apiUrl"),
            apiKey: Settings::get('provider', "presets.$preset.apiKey", ''),
            queryParams: Settings::get('provider', "presets.$preset.queryParams", []),
            httpClient: Settings::get('provider', "presets.$preset.httpClient", ''),
            metadata: Settings::get('provider', "presets.$preset.metadata", []),
        );
    }

    public static function fromArray(array $config): static {
        return new static(
            apiUrl: $config['apiUrl'] ?? $config['api_url'] ?? '',
            apiKey: $config['apiKey'] ?? $config['api_key'] ?? '',
            queryParams: $config['queryParams'] ?? $config['query_params'] ?? [],
            httpClient: $config['httpClient'] ?? $config['http_client'] ?? '',
            metadata: $config['metadata'] ?? [],
        );
    }

    public static function fromDSN(string $dsn): static {
        $data = DSN::fromString($dsn)->params();
        $preset = $data['preset'] ?? '';
        return match (true) {
            !empty($preset) => self::withOverrides(static::load($preset), $data),
            default => self::fromArray($data),
        };
    }

    private static function withOverrides(ProviderConfig $config, array $overrides): static {
        $config->apiUrl = $overrides['apiUrl'] ?? $overrides['api_url'] ?? $config->apiUrl;
        $config->apiKey = $overrides['apiKey'] ?? $overrides['api_key'] ?? $config->apiKey;
        $config->queryParams = $overrides['queryParams'] ?? $overrides['query_params'] ?? $config->queryParams;
        $config->httpClient = $overrides['httpClient'] ?? $overrides['http_client'] ?? $config->httpClient;
        $config->metadata = $overrides['metadata'] ?? $config->metadata;
        return $config;
    }

    public function toArray(): array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'queryParams' => $this->queryParams,
            'httpClient' => $this->httpClient,
            'metadata' => $this->metadata,
        ];
    }
}
