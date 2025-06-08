<?php

namespace Cognesy\Http\Config;

final class DebugConfig
{
    public const CONFIG_GROUP = 'debug';

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

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public static function fromArray(array $config): self {
        return new self(
            httpEnabled: $config['httpEnabled'] ?? false,
            httpTrace: $config['httpTrace'] ?? false,
            httpRequestUrl: $config['httpRequestUrl'] ?? true,
            httpRequestHeaders: $config['httpRequestHeaders'] ?? true,
            httpRequestBody: $config['httpRequestBody'] ?? true,
            httpResponseHeaders: $config['httpResponseHeaders'] ?? true,
            httpResponseBody: $config['httpResponseBody'] ?? true,
            httpResponseStream: $config['httpResponseStream'] ?? true,
            httpResponseStreamByLine: $config['httpResponseStreamByLine'] ?? true
        );
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