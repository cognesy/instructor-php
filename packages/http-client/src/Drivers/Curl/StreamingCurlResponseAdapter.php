<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
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
    private bool $cleanedUp = false;

    public function __construct(
        private readonly CurlHandle $handle,
        private readonly CurlMultiHandle $multi,
        private readonly SplQueue $queue,
        private readonly HeaderParser $headerParser,
        private readonly EventDispatcherInterface $events,
        private readonly int $chunkSize = 256,
        private readonly float $headerTimeoutSeconds = 5.0,
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
        try {
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
                $this->assertMultiExecSucceeded($status);

                if ($active > 0) {
                    curl_multi_select($this->multi, 0.1);
                }
            }
        } finally {
            $this->completed = true;
            $this->cleanup();
        }
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
        $timeout = max(0.0, $this->headerTimeoutSeconds);

        while ($active > 0 && $this->headerParser->statusCode() === 0 && (microtime(true) - $start) < $timeout) {
            $status = curl_multi_exec($this->multi, $active);
            $this->assertMultiExecSucceeded($status);
            if ($active > 0) {
                curl_multi_select($this->multi, 0.05);
            }
        }

        if ($this->headerParser->statusCode() !== 0) {
            return;
        }

        $elapsed = microtime(true) - $start;
        if ($elapsed >= $timeout) {
            throw new TimeoutException(
                message: sprintf('Timed out waiting for response headers after %.3f seconds', $timeout),
                duration: $elapsed,
            );
        }

        throw new NetworkException('Failed to read response headers before starting stream.');
    }

    private function assertMultiExecSucceeded(int $status): void {
        if ($status === CURLM_OK) {
            return;
        }

        $error = function_exists('curl_multi_strerror')
            ? curl_multi_strerror($status)
            : 'Unknown cURL multi error';

        throw new NetworkException("cURL multi execution failed ({$status}): {$error}");
    }

    private function cleanup(): void {
        if ($this->cleanedUp) {
            return;
        }
        $this->cleanedUp = true;

        if (!$this->handle->isClosed()) {
            curl_multi_remove_handle($this->multi, $this->handle->native());
            $this->handle->close();
        }
        curl_multi_close($this->multi);
    }

    public function __destruct() {
        if ($this->completed) {
            return;
        }
        try {
            $this->cleanup();
        } catch (\Throwable) {
            // Do not throw from destructor.
        }
    }
}
