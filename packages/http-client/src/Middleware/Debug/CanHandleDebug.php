<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

interface CanHandleDebug
{
    public function handleStream(string $line, bool $isConsolidated = false): void;
    public function handleRequest(HttpClientRequest $request): void;
    public function handleResponse(HttpClientResponse $response, array $options);
}