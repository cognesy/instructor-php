<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Stream\IterableStream;
use CurlMultiHandle;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;
use SplQueue;

/**
 * StreamingCurlResponse - Streaming Response Adapter
 *
 * Adapter for streaming curl requests where response data arrives progressively.
 * Owns curl handles and multi handle, driving execution as data is consumed.
 *
 * Memory: Zero copies - chunks go directly from curl → queue → consumer
 * Lifecycle: Handles stay alive until stream fully consumed or object destroyed
 */
final class StreamingCurlResponseAdapter implements CanAdaptHttpResponse
{
    private ?array $headers = null;
    private string $bufferedBody = '';
    private bool $completed = false;

    public function __construct(
        private readonly CurlHandle $handle,
        private readonly CurlMultiHandle $multi,
        private readonly SplQueue $queue,
        private readonly HeaderParser $headerParser,
        private readonly EventDispatcherInterface $events,
        private readonly int $chunkSize = 256,
    ) {}

    #[\Override]
    public function toHttpResponse() : HttpResponse {
        return HttpResponse::streaming(
            statusCode: $this->statusCode(),
            headers: $this->headers(),
            stream: new IterableStream($this->stream()),
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    public function statusCode(): int {
        // May need to prime the multi handle if not done yet
        if ($this->headerParser->statusCode() === 0) {
            $this->primeHandles();
        }
        return $this->headerParser->statusCode();
    }

    public function headers(): array {
        if ($this->headers === null) {
            if (empty($this->headerParser->headers())) {
                $this->primeHandles();
            }
            $this->headers = $this->headerParser->headers();
        }
        return $this->headers;
    }

    public function body(): string {
        if ($this->completed) {
            return $this->bufferedBody;
        }

        // Consume entire stream
        foreach ($this->stream() as $chunk) {
            // Accumulation happens in stream()
        }
        return $this->bufferedBody;
    }

    public function stream(): Generator {
        $active = 1;

        while (true) {
            // Yield buffered chunks
            while (!$this->queue->isEmpty()) {
                $chunk = $this->queue->dequeue();
                $this->bufferedBody .= $chunk;
                $this->events->dispatch(new HttpResponseChunkReceived($chunk));
                yield $chunk;
            }

            if ($active === 0) {
                break;
            }

            // Drive multi handle
            $status = curl_multi_exec($this->multi, $active);
            if ($status !== CURLM_OK) {
                break;
            }

            if ($active > 0) {
                curl_multi_select($this->multi, 0.1);
            }
        }

        $this->completed = true;
        $this->cleanup();
    }

    public function isStreamed(): bool {
        return true;
    }

    public function chunkSize(): int {
        return $this->chunkSize;
    }

    private function primeHandles(): void {
        $active = 1;
        $start = microtime(true);

        while ($active > 0 && $this->headerParser->statusCode() === 0 && (microtime(true) - $start) < 0.2) {
            curl_multi_exec($this->multi, $active);
            if ($active > 0) {
                curl_multi_select($this->multi, 0.05);
            }
        }
    }

    private function cleanup(): void {
        curl_multi_remove_handle($this->multi, $this->handle->native());
        curl_multi_close($this->multi);
        $this->handle->close();
    }

    public function __destruct() {
        if (!$this->completed) {
            $this->cleanup();
        }
    }
}
