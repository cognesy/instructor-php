<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use SplQueue;
use Throwable;

/**
 * CurlDriver - Lean HTTP Driver Orchestrator
 *
 * Clean curl-based HTTP driver with zero unnecessary data copies.
 * Delegates configuration to CurlFactory, header parsing to HeaderParser,
 * and response handling to specialized adapters.
 *
 * Architecture:
 * - Sync: curl_exec → body in memory → SyncCurlResponse
 * - Streaming: curl_multi → progressive chunks → StreamingCurlResponse
 */
final class CurlDriver implements CanHandleHttpRequest
{
    private readonly CurlFactory $factory;
    private readonly CurlErrorMapper $errorMapper;

    public function __construct(
        private readonly HttpClientConfig $config,
        private readonly EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is not loaded');
        }

        if ($clientInstance !== null) {
            throw new InvalidArgumentException('CurlDriver does not support external client instances');
        }

        $this->factory = new CurlFactory($config);
        $this->errorMapper = new CurlErrorMapper();
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        $this->dispatchRequestSent($request);

        return $request->isStreamed()
            ? $this->handleStreaming($request)
            : $this->handleSync($request);
    }

    // INTERNAL /////////////////////////////////////////////////////////////

    private function handleSync(HttpRequest $request): HttpResponse {
        $handle = $this->factory->createHandle($request);
        $headerParser = new HeaderParser();

        // Configure for sync
        $handle->setOption(CURLOPT_RETURNTRANSFER, true);
        $handle->setOption(CURLOPT_HEADERFUNCTION, fn($_, $line) => $this->parseHeader($headerParser, $line));

        // Create response - it will execute curl_exec internally
        $adapter = new SyncCurlResponseAdapter(
            handle: $handle,
            headerParser: $headerParser,
        );

        $response = $this->toSyncResponseOrFail($adapter, $handle, $request);
        $this->validateStatusCodeOrFail($response, $request);
        $this->dispatchResponseReceived($response->statusCode());

        return $response;
    }

    private function handleStreaming(HttpRequest $request): HttpResponse {
        $handle = $this->factory->createHandle($request);
        $headerParser = new HeaderParser();
        $queue = new SplQueue();

        // Configure for streaming
        $handle->setOption(CURLOPT_RETURNTRANSFER, false);
        $handle->setOption(CURLOPT_WRITEFUNCTION, fn($_, $data) => $this->enqueueChunk($queue, $data));
        $handle->setOption(CURLOPT_HEADERFUNCTION, fn($_, $line) => $this->parseHeader($headerParser, $line));

        // Setup multi handle
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $handle->native());

        $response = new StreamingCurlResponseAdapter(
            handle: $handle,
            multi: $multi,
            queue: $queue,
            headerParser: $headerParser,
            events: $this->events,
            chunkSize: $this->config->streamChunkSize ?? 256,
            headerTimeoutSeconds: (float) $this->config->streamHeaderTimeout,
        );

        $httpResponse = $response->toHttpResponse();
        $this->validateStatusCodeOrFail($httpResponse, $request);
        $this->dispatchResponseReceived($httpResponse->statusCode());

        return $httpResponse;
    }

    private function parseHeader(HeaderParser $parser, string $line): int {
        $parser->parse($line);
        return strlen($line);
    }

    private function enqueueChunk(SplQueue $queue, string $data): int {
        if ($data !== '') {
            $queue->enqueue($data);
        }
        return strlen($data);
    }

    private function handleError(CurlHandle $handle, HttpRequest $request): never {
        $exception = $this->errorMapper->mapError(
            $handle->errorCode(),
            $handle->error() ?? 'Unknown error',
            $request,
        );

        $this->dispatchRequestFailed($exception, $request);
        throw $exception;
    }

    private function toSyncResponseOrFail(
        SyncCurlResponseAdapter $adapter,
        CurlHandle $handle,
        HttpRequest $request,
    ): HttpResponse {
        try {
            return $adapter->toHttpResponse();
        } catch (Throwable) {
            $this->handleError($handle, $request);
        }
    }

    private function validateStatusCodeOrFail(HttpResponse $response, HttpRequest $request): void {
        if (!$this->config->failOnError || $response->statusCode() < 400) {
            return;
        }

        $exception = HttpExceptionFactory::fromStatusCode(
            $response->statusCode(),
            $request,
            $response,
            null
        );

        $this->dispatchRequestFailed($exception, $request);
        throw $exception;
    }

    private function dispatchRequestSent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
        ]));
    }

    private function dispatchResponseReceived(int $statusCode): void {
        $this->events->dispatch(new HttpResponseReceived(['statusCode' => $statusCode]));
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'errors' => $exception->getMessage(),
        ]));
    }
}
