<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Streaming HttpResponse backed by PHP cURL multi API.
 *
 * Owns the cURL handles for the lifetime of the stream and cleans them up
 * once the transfer completes.
 */
class CurlStreamingHttpResponse implements HttpResponse
{
    /** @var \CurlHandle */
    private $curl;
    /** @var \CurlMultiHandle */
    private $multi;
    /** @var \SplQueue<string> */
    private \SplQueue $queue;
    private EventDispatcherInterface $events;
    private HttpRequest $request;
    private HttpClientConfig $config;
    private int $streamChunkSize;
    /** @var array<string, array<string>> */
    private array $headers;
    private int $statusCode;
    private string $bufferedBody = '';
    private bool $completed = false;

    /**
     * @param array<string, array<string>> $headers
     */
    public function __construct(
        \CurlHandle $curl,
        \CurlMultiHandle $multi,
        \SplQueue $queue,
        EventDispatcherInterface $events,
        HttpRequest $request,
        HttpClientConfig $config,
        array &$headers,
        int &$statusCode,
        int $streamChunkSize = 256,
    ) {
        $this->curl = $curl;
        $this->multi = $multi;
        $this->queue = $queue;
        $this->events = $events;
        $this->request = $request;
        $this->config = $config;
        $this->headers = &$headers;
        $this->statusCode = &$statusCode;
        $this->streamChunkSize = $streamChunkSize;
    }

    #[\Override]
    public function statusCode(): int {
        $code = $this->statusCode;
        if ($code > 0) {
            return $code;
        }
        $tries = 0;
        while ($this->statusCode === 0 && $tries < 2) {
            $active = 1;
            $status = curl_multi_exec($this->multi, $active);
            if ($status !== CURLM_OK) {
                break;
            }
            if ($active > 0) {
                curl_multi_select($this->multi, 0.01);
            }
            $tries++;
        }
        $code = $this->statusCode;
        if ($code > 0) {
            return $code;
        }
        return (int) curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    }

    /**
     * @return array<string, array<string>>
     */
    #[\Override]
    public function headers(): array {
        return $this->headers;
    }

    #[\Override]
    public function body(): string {
        if ($this->completed) {
            return $this->bufferedBody;
        }
        foreach ($this->stream($this->streamChunkSize) as $chunk) {
            $this->bufferedBody .= $chunk;
        }
        return $this->bufferedBody;
    }

    #[\Override]
    public function isStreamed(): bool {
        return true;
    }

    /**
     * Drive the transfer and yield chunks as they arrive.
     *
     * @return Generator<string>
     */
    #[\Override]
    public function stream(?int $chunkSize = null): Generator {
        $active = 1;
        while (true) {
            while (!$this->queue->isEmpty()) {
                $chunk = $this->queue->dequeue();
                $this->bufferedBody .= $chunk;
                $this->events->dispatch(new HttpResponseChunkReceived($chunk));
                yield $chunk;
            }

            if ($active === 0) {
                break;
            }

            $status = curl_multi_exec($this->multi, $active);
            if ($status !== CURLM_OK) {
                break;
            }
            if ($active > 0) {
                curl_multi_select($this->multi, 0.1);
            }
        }

        $this->completed = true;
        $this->finalize();
    }

    private function finalize(): void {
        $code = $this->statusCode();
        $this->events->dispatch(new HttpResponseReceived(['statusCode' => $code]));

        curl_multi_remove_handle($this->multi, $this->curl);
        curl_multi_close($this->multi);
        curl_close($this->curl);

        if (!$this->config->failOnError || $code < 400) {
            return;
        }

        $response = new CurlHttpResponse(
            statusCode: $code,
            headers: $this->headers,
            body: $this->bufferedBody,
            isStreamed: true,
            events: $this->events,
            streamChunkSize: $this->streamChunkSize,
        );

        $exception = HttpExceptionFactory::fromStatusCode(
            $code,
            $this->request,
            $response,
            0.0
        );
        throw $exception;
    }
}
