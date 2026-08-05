<?php declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\HttpClient;

use Cognesy\Events\Support\ListenerGate;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Stream\IterableStream;
use Illuminate\Http\Client\Response;
use Psr\EventDispatcher\EventDispatcherInterface;

class LaravelHttpResponseAdapter implements CanAdaptHttpResponse
{
    public function __construct(
        private Response $response,
        private EventDispatcherInterface $events,
        private bool $streaming = false,
        private int $streamChunkSize = HttpClientConfig::DEFAULT_STREAM_CHUNK_SIZE,
        private string $requestId = '',
    ) {}

    #[\Override]
    public function toHttpResponse(): HttpResponse
    {
        if ($this->streaming) {
            return HttpResponse::streaming(
                statusCode: $this->response->status(),
                headers: $this->response->headers(),
                stream: new IterableStream($this->stream()),
            );
        }

        return HttpResponse::sync(
            statusCode: $this->response->status(),
            headers: $this->response->headers(),
            body: $this->response->body(),
        );
    }

    /** @return \Generator<string> */
    private function stream(): \Generator
    {
        $stream = $this->response->toPsrResponse()->getBody();
        // Resolved once per stream, as in StreamingCurlResponseAdapter and
        // PsrHttpResponseAdapter: the event object is the dispatch() argument, so
        // without asking first it is built for every chunk whether or not anyone
        // consumes it.
        $emitChunks = ListenerGate::wants($this->events, HttpResponseChunkReceived::class);

        while (!$stream->eof()) {
            $chunk = $stream->read($this->streamChunkSize);
            if ($emitChunks) {
                // The payload must carry requestId and chunk as keys. Dispatching the
                // raw string instead — as this adapter used to — makes
                // HttpClientTelemetryProjector::onChunkReceived() read null for both
                // and return early, so every streamed chunk under the Laravel driver
                // was silently invisible to telemetry.
                $this->events->dispatch(new HttpResponseChunkReceived([
                    'requestId' => $this->requestId,
                    'chunk' => $chunk,
                ]));
            }
            yield $chunk;
        }
    }
}
