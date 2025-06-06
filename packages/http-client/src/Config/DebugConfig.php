<?php

namespace Cognesy\Http\Config;

final class DebugConfig
{
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
        return new self(
            httpEnabled: $config['http_enabled'] ?? false,
            httpTrace: $config['http_trace'] ?? false,
            httpRequestUrl: $config['http_requestUrl'] ?? true,
            httpRequestHeaders: $config['http_requestHeaders'] ?? true,
            httpRequestBody: $config['http_requestBody'] ?? true,
            httpResponseHeaders: $config['http_responseHeaders'] ?? true,
            httpResponseBody: $config['http_responseBody'] ?? true,
            httpResponseStream: $config['http_responseStream'] ?? true,
            httpResponseStreamByLine: $config['http_responseStreamByLine'] ?? true
        );
    }

    public function toArray() : array {
        return [
            'http_enabled' => $this->httpEnabled,
            'http_trace' => $this->httpTrace,
            'http_requestUrl' => $this->httpRequestUrl,
            'http_requestHeaders' => $this->httpRequestHeaders,
            'http_requestBody' => $this->httpRequestBody,
            'http_responseHeaders' => $this->httpResponseHeaders,
            'http_responseBody' => $this->httpResponseBody,
            'http_responseStream' => $this->httpResponseStream,
            'http_responseStreamByLine' => $this->httpResponseStreamByLine,
        ];
    }
}