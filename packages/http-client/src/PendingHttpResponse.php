<?php declare(strict_types=1);

namespace Cognesy\Http;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Generator;

/**
 * Deferred request execution with explicit sync and stream response caches.
 */
class PendingHttpResponse
{
    private readonly CanHandleHttpRequest $driver;
    private readonly HttpRequest $request;

    private ?HttpResponse $syncResponse = null;
    private ?HttpResponse $streamedResponse = null;

    public function __construct(
        HttpRequest $request,
        CanHandleHttpRequest $driver,
    ) {
        $this->request = clone $request;
        $this->driver = $driver;
    }

    public function get(): HttpResponse {
        return $this->getConfiguredResponse();
    }

    public function statusCode(): int {
        return $this
            ->getMetadataResponse()
            ->statusCode();
    }

    public function headers(): array {
        return $this
            ->getMetadataResponse()
            ->headers();
    }

    public function content(): string {
        return $this
            ->getConfiguredResponse()
            ->body();
    }

    /**
     * Stream response chunks using the configured adapter chunk size.
     *
     * Chunk sizing is controlled by adapters and HttpClientConfig::streamChunkSize.
     * Streaming and sync execution are cached independently to avoid mode collisions.
     *
     * @return Generator<string>
     */
    public function stream(): Generator {
        yield from $this
            ->getStreamedResponse()
            ->stream();
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////

    private function getSyncResponse(): HttpResponse {
        if ($this->syncResponse === null) {
            $this->syncResponse = $this->driver->handle($this->makeSyncRequest());
        }
        return $this->syncResponse;
    }

    private function getStreamedResponse(): HttpResponse {
        if ($this->streamedResponse === null) {
            $this->streamedResponse = $this->driver->handle($this->makeStreamedRequest());
        }
        return $this->streamedResponse;
    }

    private function getMetadataResponse(): HttpResponse {
        return match (true) {
            $this->syncResponse !== null => $this->syncResponse,
            $this->streamedResponse !== null => $this->streamedResponse,
            default => $this->getConfiguredResponse(),
        };
    }

    private function getConfiguredResponse(): HttpResponse {
        return match (true) {
            $this->request->isStreamed() => $this->getStreamedResponse(),
            default => $this->getSyncResponse(),
        };
    }

    private function makeSyncRequest(): HttpRequest {
        return (clone $this->request)->withStreaming(false);
    }

    private function makeStreamedRequest(): HttpRequest {
        return (clone $this->request)->withStreaming(true);
    }
}
