<?php

namespace Cognesy\Http\Config;

final class DebugConfig
{
    public function __construct(
        public bool $httpEnabled = false,
        public bool $httpTrace = false,
        public bool $httpRequestUrl = true,
        public bool $httpRequestHeaders = true,
        public bool $httpRequestBody = true,
        public bool $httpResponseHeaders = true,
        public bool $httpResponseBody = true,
        public bool $httpResponseStream = true,
        public bool $httpResponseStreamByLine = true,
    ) {}

    public static function fromArray(array $config): self {
        return new self(
            $config['httpEnabled'] ?? true,
            $config['httpTrace'] ?? false,
            $config['httpRequestUrl'] ?? true,
            $config['httpRequestHeaders'] ?? true,
            $config['httpRequestBody'] ?? true,
            $config['httpResponseHeaders'] ?? true,
            $config['httpResponseBody'] ?? true,
            $config['httpResponseStream'] ?? true,
            $config['httpResponseStreamByLine'] ?? true
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