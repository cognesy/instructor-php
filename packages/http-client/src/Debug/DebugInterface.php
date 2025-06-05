<?php

namespace Cognesy\Http\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

interface DebugInterface
{
    public function config() : DebugConfig;
    public function tryDumpStream(string $line, bool $isConsolidated = false): void;
    public function tryDumpRequest(HttpClientRequest $request): void;
    public function tryDumpTrace();
    public function tryDumpResponse(HttpClientResponse $response, array $options);
    public function tryDumpUrl(string $url) : void;
}
