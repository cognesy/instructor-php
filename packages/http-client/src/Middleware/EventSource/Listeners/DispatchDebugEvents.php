<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource\Listeners;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\DebugRequestBodyUsed;
use Cognesy\Http\Events\DebugRequestHeadersUsed;
use Cognesy\Http\Events\DebugRequestURLUsed;
use Cognesy\Http\Events\DebugResponseBodyReceived;
use Cognesy\Http\Events\DebugResponseHeadersReceived;
use Cognesy\Http\Events\DebugStreamChunkReceived;
use Cognesy\Http\Events\DebugStreamLineReceived;
use Psr\EventDispatcher\EventDispatcherInterface;

class DispatchDebugEvents implements CanListenToHttpEvents
{
    public function __construct(
        protected readonly DebugConfig $config,
        protected readonly EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function onRequestReceived(HttpRequest $request): void {
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

    #[\Override]
    public function onStreamChunkReceived(HttpRequest $request, HttpResponse $response, string $chunk): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $this->events->dispatch(new DebugStreamChunkReceived(['chunk' => $chunk]));
    }

    #[\Override]
    public function onStreamEventAssembled(HttpRequest $request, HttpResponse $response, string $line): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $this->events->dispatch(new DebugStreamLineReceived(['line' => $line]));
    }

    #[\Override]
    public function onResponseReceived(HttpRequest $request, HttpResponse $response): void {
        if ($this->config->httpResponseHeaders) {
            $this->events->dispatch(new DebugResponseHeadersReceived(['headers' => $response->headers()]));
        }
        if ($this->config->httpResponseBody && !$response->isStreamed()) {
            $this->events->dispatch(new DebugResponseBodyReceived(['body' => $response->body()]));
        }
    }
}