<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Events\DebugRequestBodyUsed;
use Cognesy\Http\Events\DebugRequestHeadersUsed;
use Cognesy\Http\Events\DebugRequestURLUsed;
use Cognesy\Http\Events\DebugResponseBodyReceived;
use Cognesy\Http\Events\DebugResponseHeadersReceived;
use Cognesy\Http\Events\DebugStreamChunkReceived;
use Cognesy\Http\Events\DebugStreamLineReceived;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventsDebug implements CanHandleDebug
{
    public function __construct(
        protected readonly DebugConfig $config,
        protected readonly EventDispatcherInterface $events,
    ) {}

    public function handleStreamEvent(string $line): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $this->events->dispatch(new DebugStreamLineReceived(['line' => $line]));
    }

    public function handleStreamChunk(string $chunk): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $this->events->dispatch(new DebugStreamChunkReceived(['chunk' => $chunk]));
    }

    public function handleRequest(HttpClientRequest $request): void {
        if ($this->config->httpRequestUrl) {
            $this->events->dispatch(new DebugRequestUrlUsed(['url' => $request->url()]));
        }
        if ($this->config->httpRequestHeaders) {
            $this->events->dispatch(new DebugRequestHeadersUsed(['headers' => $request->headers()]));
        }
        if ($this->config->httpRequestBody) {
            $this->events->dispatch(new DebugRequestBodyUsed(['body' => $request->body()->toString()]));
        }
    }

    public function handleTrace() {
        if ($this->config->httpTrace) {
            // ...
        }
    }

    public function handleResponse(HttpClientResponse $response) : void {
        if ($this->config->httpResponseHeaders) {
            $this->events->dispatch(new DebugResponseHeadersReceived(['headers' => $response->headers()]));
        }
        if ($this->config->httpResponseBody && !$response->isStreamed()) {
            $this->events->dispatch(new DebugResponseBodyReceived(['body' => $response->body()]));
        }
    }
}