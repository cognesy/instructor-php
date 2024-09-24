<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

class DebugConfig
{
    public function __construct(
        public bool $enabled = false,
        public bool $detailed = false,
        public bool $requestHeaders = true,
        public bool $requestBody = true,
        public bool $responseHeaders = true,
        public bool $responseBody = true,
    ) {}

    public static function fromArray(array $config) : self {
        return new DebugConfig(
            enabled: $config['enabled'] ?? false,
            detailed: $config['detailed'] ?? false,
            requestHeaders: $config['request_headers'] ?? true,
            requestBody: $config['request_body'] ?? true,
            responseHeaders: $config['response_headers'] ?? true,
            responseBody: $config['response_body'] ?? true,
        );
    }

    public function needsDetails() : bool {
        return $this->enabled && $this->detailed;
    }
}