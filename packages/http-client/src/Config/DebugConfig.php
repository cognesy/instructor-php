<?php

namespace Cognesy\Http\Config;

use Cognesy\Config\Exceptions\ConfigurationException;
use Throwable;

final class DebugConfig
{
    public const CONFIG_GROUP = 'debug';

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        public readonly bool $httpEnabled = false,
        public readonly bool $httpTrace = false,
        public readonly bool $httpRequestUrl = true,
        public readonly bool $httpRequestHeaders = true,
        public readonly bool $httpRequestBody = true,
        public readonly bool $httpResponseHeaders = true,
        public readonly bool $httpResponseBody = true,
        public readonly bool $httpResponseStream = true,
        public readonly bool $httpResponseStreamByLine = true,
    ) {}

    public static function fromArray(array $config): self {
        try {
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Invalid configuration for DebugConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
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
            'httpEnabled' => $this->httpEnabled,
            'httpTrace' => $this->httpTrace,
            'httpRequestUrl' => $this->httpRequestUrl,
            'httpRequestHeaders' => $this->httpRequestHeaders,
            'httpRequestBody' => $this->httpRequestBody,
            'httpResponseHeaders' => $this->httpResponseHeaders,
            'httpResponseBody' => $this->httpResponseBody,
            'httpResponseStream' => $this->httpResponseStream,
            'httpResponseStreamByLine' => $this->httpResponseStreamByLine,
        ];
    }
}