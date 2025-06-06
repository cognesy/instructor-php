<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

interface CanHandleDebug
{
    public function handleRequest(HttpClientRequest $request) : void;
    public function handleResponse(HttpClientResponse $response) : void;
    public function handleStreamChunk(string $chunk) : void;
    public function handleStreamEvent(string $line) : void;
}