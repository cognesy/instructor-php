<?php

namespace Cognesy\Utils\Debug;

use Cognesy\Utils\Settings;

class DebugConfig
{
    public function __construct(
        public bool $httpEnabled = true,
        public bool $httpTrace = false,
        public bool $httpRequestUrl = true,
        public bool $httpRequestHeaders = true,
        public bool $httpRequestBody = true,
        public bool $httpResponseHeaders = true,
        public bool $httpResponseBody = true,
        public bool $httpResponseStream = true,
        public bool $httpResponseStreamByLine = true,
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public static function allEnabled(): self
    {
        return new self(
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true,
            true
        );
    }

    public static function load(): self
    {
        return new self(
            Settings::get('debug', 'http.enabled', false),
            Settings::get('debug', 'http.trace', false),
            Settings::get('debug', 'http.requestUrl', true),
            Settings::get('debug', 'http.requestHeaders', true),
            Settings::get('debug', 'http.requestBody', true),
            Settings::get('debug', 'http.responseHeaders', true),
            Settings::get('debug', 'http.responseBody', true),
            Settings::get('debug', 'http.responseStream', true),
            Settings::get('debug', 'http.responseStreamByLine', true)
        );
    }

    public static function fromArray(array $config): self
    {
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
}