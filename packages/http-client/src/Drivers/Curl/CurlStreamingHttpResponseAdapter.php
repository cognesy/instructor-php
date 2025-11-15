<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpResponseChunkReceived;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use CurlHandle;
use CurlMultiHandle;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;
use SplQueue;

/**
 * Streaming HttpResponse backed by PHP cURL multi API.
 *
 * Owns the cURL handles for the lifetime of the stream and cleans them up
 * once the transfer completes.
 */
class CurlStreamingHttpResponseAdapter implements CanAdaptHttpResponse
{
    /** @var CurlHandle */
    private $curl;
    /** @var CurlMultiHandle */
    private $multi;
    /** @var SplQueue<string> */
    private SplQueue $queue;
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
        CurlHandle $curl,
        CurlMultiHandle $multi,
        SplQueue $queue,
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
    public function toHttpResponse() : HttpResponse {
        return new HttpResponse(
            statusCode: $this->statusCode(),
            body: '', // Don't consume stream eagerly for streaming responses
            headers: $this->headers,
            isStreamed: true,
            stream: $this->stream(),
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function statusCode(): int {
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

    private function body(): string {
        if ($this->completed) {
            return $this->bufferedBody;
        }
        foreach ($this->stream() as $chunk) {
            $this->bufferedBody .= $chunk;
        }
        return $this->bufferedBody;
    }

    private function stream(): Generator {
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

        $response = new CurlHttpResponseAdapter(
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
            $response->toHttpResponse(),
            0.0
        );
        throw $exception;
    }
}
