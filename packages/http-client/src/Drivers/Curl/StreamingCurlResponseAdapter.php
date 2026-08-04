<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpStreamCompleted;
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
 * Memory: chunks go from curl → queue → consumer without a copy, as long as a write
 *   fits within chunkSize. At the default chunkSize (CURL_MAX_WRITE_SIZE) curl writes
 *   always fit, so the common path copies nothing; a smaller configured chunkSize
 *   trades one substr() per fragment for finer-grained delivery.
 * Lifecycle: Handles stay alive until stream fully consumed or object destroyed
 */
final class StreamingCurlResponseAdapter implements CanAdaptHttpResponse
{
    private ?array $headers = null;
    private bool $completed = false;
    private bool $cleanedUp = false;
    private readonly CurlErrorMapper $errorMapper;

    public function __construct(
        private readonly CurlHandle $handle,
        private readonly CurlMultiHandle $multi,
        private readonly SplQueue $queue,
        private readonly HeaderParser $headerParser,
        private readonly EventDispatcherInterface $events,
        private readonly string $requestId = '',
        private readonly int $chunkSize = HttpClientConfig::DEFAULT_STREAM_CHUNK_SIZE,
        private readonly float $headerTimeoutSeconds = 5.0,
        private readonly ?HttpRequest $request = null,
    ) {
        $this->errorMapper = new CurlErrorMapper();
    }

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

    public function stream(): Generator {
        $active = 1;
        $outcome = 'abandoned';
        $bytes = 0;
        $chunkCount = 0;
        $error = null;
        $emitChunks = $this->shouldEmitChunkEvents();
        try {
            while (true) {
                // Yield buffered chunks
                while (!$this->queue->isEmpty()) {
                    foreach ($this->splitChunk($this->queue->dequeue()) as $chunk) {
                        $bytes += strlen($chunk);
                        $chunkCount++;
                        if ($emitChunks) {
                            $this->events->dispatch(new HttpResponseChunkReceived([
                                'requestId' => $this->requestId,
                                'chunk' => $chunk,
                            ]));
                        }
                        yield $chunk;
                    }
                }

                if ($active === 0) {
                    break;
                }

                // Drive multi handle
                $status = curl_multi_exec($this->multi, $active);
                $this->assertMultiExecSucceeded($status);
                $this->throwIfTransferFailed();

                if ($active > 0) {
                    curl_multi_select($this->multi, 0.1);
                }
            }
            $outcome = 'completed';
        } catch (\Throwable $error) {
            $outcome = 'failed';
            throw $error;
        } finally {
            $this->completed = true;
            $this->cleanup();
            $payload = [
                'requestId' => $this->requestId,
                'outcome' => $outcome,
                'bytes' => $bytes,
                'chunks' => $chunkCount,
            ];
            $payload = match (true) {
                $error !== null => [...$payload, 'error' => $error->getMessage()],
                default => $payload,
            };

            $this->events->dispatch(new HttpStreamCompleted($payload));
        }
    }

    public function isStreamed(): bool {
        return true;
    }

    /**
     * Whether a chunk event would reach anybody, resolved once per stream.
     *
     * The event object is the argument to dispatch(), so it is built whether or not
     * anything consumes it. On a 205 KB SSE response that was 822 objects and 994 KB
     * of allocation, two thirds of it Uuid::uuid4()'s CSPRNG draw, for listeners that
     * usually do not exist. Asking once costs an array lookup.
     *
     * A dispatcher that cannot answer is assumed to listen — the degradation the
     * CanCheckListeners contract prescribes.
     */
    private function shouldEmitChunkEvents(): bool {
        return match (true) {
            $this->events instanceof CanCheckListeners => $this->events->hasListenersFor(HttpResponseChunkReceived::class),
            default => true,
        };
    }

    /**
     * Cap a curl write at chunkSize, copying only when it actually exceeds it.
     *
     * chunkSize is an upper bound, not a target. curl already frames its writes at
     * CURL_MAX_WRITE_SIZE, so at the default the common path yields the write itself
     * and makes no copy at all. Re-slicing every write into 256-byte pieces cost 822
     * substr() copies per 205 KB response for output EventSourceStream reassembled
     * anyway — parse time is identical from either framing.
     *
     * A chunkSize of 0 or less means "no upper bound". It used to mean the opposite:
     * max(1, 0) yielded one-byte fragments, so presets/symfony.yaml's `streamChunkSize: 0`
     * would have produced 8x more fragments than the old 256 default rather than none.
     *
     * @return Generator<string>
     */
    private function splitChunk(string $chunk): Generator {
        $chunkSize = $this->chunkSize;
        $length = strlen($chunk);

        // The old for-loop yielded nothing for an empty chunk; keep that. CurlDriver
        // never enqueues one, but the adapter accepts a queue directly.
        if ($length === 0) {
            return;
        }

        if ($chunkSize <= 0 || $length <= $chunkSize) {
            yield $chunk;

            return;
        }

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            yield substr($chunk, $offset, $chunkSize);
        }
    }

    private function primeHandles(): void {
        $active = 1;
        $start = microtime(true);
        $timeout = max(0.0, $this->headerTimeoutSeconds);

        while ($active > 0 && $this->headerParser->statusCode() === 0 && (microtime(true) - $start) < $timeout) {
            $status = curl_multi_exec($this->multi, $active);
            $this->assertMultiExecSucceeded($status);
            $this->throwIfTransferFailed();
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

    private function throwIfTransferFailed(): void {
        while (($info = curl_multi_info_read($this->multi)) !== false) {
            if (($info['msg'] ?? null) !== CURLMSG_DONE) {
                continue;
            }

            $result = (int) ($info['result'] ?? CURLE_OK);
            if ($result === CURLE_OK) {
                continue;
            }

            $handle = $info['handle'] ?? null;
            $message = is_object($handle) && $handle instanceof \CurlHandle
                ? curl_error($handle)
                : '';
            $message = $message !== '' ? $message : "cURL transfer failed ({$result})";

            throw $this->errorMapper->mapError($result, $message, $this->request);
        }
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
