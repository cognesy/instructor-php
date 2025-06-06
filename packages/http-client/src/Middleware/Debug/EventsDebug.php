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

    public function handleStream(string $line, bool $isConsolidated = false): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $this->events->dispatch(match($isConsolidated) {
            true => new DebugStreamLineReceived($line),
            false => new DebugStreamChunkReceived($line),
        });
    }

    public function handleRequest(HttpClientRequest $request): void {
        if ($this->config->httpRequestUrl) {
            $this->events->dispatch(new DebugRequestUrlUsed($request->url()));
        }
        if ($this->config->httpRequestHeaders) {
            $this->events->dispatch(new DebugRequestHeadersUsed($request->headers()));
        }
        if ($this->config->httpRequestBody) {
            $this->events->dispatch(new DebugRequestBodyUsed($request->body()->toString()));
        }
    }

    public function handleTrace() {
        if ($this->config->httpTrace) {
            // ...
        }
    }

    public function handleResponse(HttpClientResponse $response, array $options) {
        if ($this->config->httpResponseHeaders) {
            $this->events->dispatch(new DebugResponseHeadersReceived($response->headers()));
        }
        if ($this->config->httpResponseBody && $options['stream'] === false) {
            $this->events->dispatch(new DebugResponseBodyReceived($response->body()));
        }
    }
}