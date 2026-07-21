<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\EventSource\Listeners;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\DebugRequestBodyUsed;
use Cognesy\Http\Events\DebugRequestHeadersUsed;
use Cognesy\Http\Events\DebugRequestURLUsed;
use Cognesy\Http\Events\DebugResponseBodyReceived;
use Cognesy\Http\Events\DebugResponseHeadersReceived;
use Cognesy\Http\Events\DebugStreamChunkReceived;
use Cognesy\Http\Events\DebugStreamLineReceived;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;
use Psr\EventDispatcher\EventDispatcherInterface;

class DispatchDebugEvents implements CanListenToHttpEvents
{
    /** @var array<string, int> */
    private array $capturedStreamBytes = [];

    public function __construct(
        protected readonly DebugConfig $config,
        protected readonly EventDispatcherInterface $events,
    ) {}

    #[\Override]
    public function onRequestReceived(HttpRequest $request): void {
        if ($this->config->httpRequestUrl) {
            $this->events->dispatch(new DebugRequestURLUsed([
                'url' => DefaultRequestRedactor::redactUrl($request->url()),
            ]));
        }
        if ($this->config->httpRequestHeaders) {
            $this->events->dispatch(new DebugRequestHeadersUsed([
                'headers' => DefaultRequestRedactor::redactHeaders($request->headers()),
            ]));
        }
        if ($this->config->httpRequestBody) {
            $this->events->dispatch(new DebugRequestBodyUsed([
                'body' => $this->safeBody($request->body()->toString()),
            ]));
        }
    }

    #[\Override]
    public function onStreamChunkReceived(HttpRequest $request, HttpResponse $response, string $chunk): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $safeChunk = $this->safeStreamPayload($request, $chunk);
        if ($safeChunk === null) {
            return;
        }
        $this->events->dispatch(new DebugStreamChunkReceived(['chunk' => $safeChunk]));
    }

    #[\Override]
    public function onStreamEventAssembled(HttpRequest $request, HttpResponse $response, string $line): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $safeLine = $this->safeStreamPayload($request, $line);
        if ($safeLine === null) {
            return;
        }
        $this->events->dispatch(new DebugStreamLineReceived(['line' => $safeLine]));
    }

    #[\Override]
    public function onResponseReceived(HttpRequest $request, HttpResponse $response): void {
        if ($this->config->httpResponseHeaders) {
            $this->events->dispatch(new DebugResponseHeadersReceived([
                'headers' => DefaultRequestRedactor::redactHeaders($response->headers()),
            ]));
        }
        if ($this->config->httpResponseBody && !$response->isStreamed()) {
            $this->events->dispatch(new DebugResponseBodyReceived([
                'body' => $this->safeBody($response->body()),
            ]));
        }
    }

    private function safeBody(string $body): string {
        $redacted = DefaultRequestRedactor::redactBody($body);
        $limit = max(0, $this->config->httpBodyMaxBytes);

        return substr($redacted, 0, $limit);
    }

    private function safeStreamPayload(HttpRequest $request, string $payload): ?string {
        $limit = max(0, $this->config->httpBodyMaxBytes);
        $captured = $this->capturedStreamBytes[$request->id] ?? 0;
        if ($captured >= $limit) {
            return null;
        }

        $payload = DefaultRequestRedactor::redactBody($payload);
        $payload = substr($payload, 0, $limit - $captured);
        if ($payload === '') {
            return null;
        }

        $this->capturedStreamBytes[$request->id] = $captured + strlen($payload);
        return $payload;
    }
}
