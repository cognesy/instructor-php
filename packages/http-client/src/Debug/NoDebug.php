<?php

namespace Cognesy\Http\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class NoDebug
{
    private DebugConfig $config;

    public function __construct(?DebugConfig $config = null) {
        $this->config = $config ?? new DebugConfig();
    }

    public function config() : DebugConfig {
        return $this->config;
    }

    public function tryDumpStream(string $line, bool $isConsolidated = false): void {}

    public function tryDumpRequest(HttpClientRequest $request): void {}

    public function tryDumpTrace() {}

    public function tryDumpResponse(HttpClientResponse $response, array $options) {}

    public function tryDumpUrl(string $url) : void {}
}