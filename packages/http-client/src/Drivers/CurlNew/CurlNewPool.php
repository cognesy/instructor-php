<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\CurlNew;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Utils\Result\Result;
use CurlMultiHandle;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * CurlNewPool - Clean Pool Implementation for Concurrent Requests
 *
 * Leverages the clean CurlNew architecture:
 * - CurlFactory for configuration (zero duplication)
 * - CurlHandle for resource lifecycle
 * - HeaderParser for proper header extraction
 * - SyncCurlResponse adapters for results
 *
 * Architecture:
 * 1. Create handles via CurlFactory
 * 2. Add to curl_multi
 * 3. Drive event loop
 * 4. Process completions → SyncCurlResponse
 * 5. Automatic cleanup via destructors
 */
final class CurlNewPool implements CanHandleRequestPool
{
    private readonly CurlFactory $factory;

    public function __construct(
        private readonly HttpClientConfig $config,
        private readonly EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is not loaded');
        }

        if ($clientInstance !== null) {
            throw new InvalidArgumentException('CurlNewPool does not support external client instances');
        }

        $this->factory = new CurlFactory($config);
    }

    #[\Override]
    public function pool(array $requests, ?int $maxConcurrent = null): array {
        if (empty($requests)) {
            return [];
        }

        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent ?? 5;
        $multiHandle = curl_multi_init();

        $results = $this->executePool(
            multiHandle: $multiHandle,
            requests: $requests,
            maxConcurrent: $maxConcurrent,
        );

        curl_multi_close($multiHandle);

        return $results;
    }

    // INTERNAL /////////////////////////////////////////////

    /**
     * Execute pool with rolling window of concurrent requests
     *
     * @param array<HttpRequest> $requests
     * @return array<Result>
     */
    private function executePool(
        CurlMultiHandle $multiHandle,
        array $requests,
        int $maxConcurrent,
    ): array {
        $queue = array_values($requests);
        $queueIndex = 0;
        $responses = [];

        /** @var array<int, array{handle: CurlHandle, parser: HeaderParser, request: HttpRequest, index: int}> */
        $active = [];

        // Initialize first batch
        $this->fillWindow($multiHandle, $queue, $queueIndex, $maxConcurrent, $active);

        // Event loop
        $this->driveMultiHandle($multiHandle, $queue, $queueIndex, $maxConcurrent, $active, $responses);

        // Finalize
        return $this->finalizeResponses($responses);
    }

    /**
     * Fill the rolling window up to max concurrency
     *
     * @param array<HttpRequest> $queue
     * @param array<int, array{handle: CurlHandle, parser: HeaderParser, request: HttpRequest, index: int}> $active
     */
    private function fillWindow(
        CurlMultiHandle $multiHandle,
        array $queue,
        int &$queueIndex,
        int $maxConcurrent,
        array &$active,
    ): void {
        while ($queueIndex < count($queue) && count($active) < $maxConcurrent) {
            $request = $queue[$queueIndex];
            $this->attachRequest($multiHandle, $request, $queueIndex, $active);
            $queueIndex++;
        }
    }

    /**
     * Drive curl_multi event loop
     *
     * @param array<HttpRequest> $queue
     * @param array<int, array{handle: CurlHandle, parser: HeaderParser, request: HttpRequest, index: int}> $active
     * @param array<int, Result> $responses
     */
    private function driveMultiHandle(
        CurlMultiHandle $multiHandle,
        array $queue,
        int &$queueIndex,
        int $maxConcurrent,
        array &$active,
        array &$responses,
    ): void {
        do {
            $status = curl_multi_exec($multiHandle, $stillRunning);

            if ($status !== CURLM_OK) {
                break;
            }

            // Process completed transfers
            $this->processCompleted($multiHandle, $queue, $queueIndex, $maxConcurrent, $active, $responses);

            if ($stillRunning > 0) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($stillRunning > 0 || !empty($active));
    }

    /**
     * Process all completed transfers
     *
     * @param array<HttpRequest> $queue
     * @param array<int, array{handle: CurlHandle, parser: HeaderParser, request: HttpRequest, index: int}> $active
     * @param array<int, Result> $responses
     */
    private function processCompleted(
        CurlMultiHandle $multiHandle,
        array $queue,
        int &$queueIndex,
        int $maxConcurrent,
        array &$active,
        array &$responses,
    ): void {
        while ($info = curl_multi_info_read($multiHandle)) {
            if ($info['msg'] !== CURLMSG_DONE) {
                continue;
            }

            $nativeHandle = $info['handle'];
            $handleId = spl_object_id($nativeHandle);

            if (!isset($active[$handleId])) {
                continue; // Already processed
            }

            $context = $active[$handleId];

            try {
                $response = $this->createResponse($context['handle'], $context['parser'], $context['request']);
                $responses[$context['index']] = Result::success($response);
            } catch (\Throwable $e) {
                if ($this->config->failOnError) {
                    throw $e;
                }
                $responses[$context['index']] = Result::failure($e);
            }

            // Cleanup
            $this->detachHandle($multiHandle, $context['handle'], $active);

            // Fill window with next request
            if ($queueIndex < count($queue) && count($active) < $maxConcurrent) {
                $nextRequest = $queue[$queueIndex];
                $this->attachRequest($multiHandle, $nextRequest, $queueIndex, $active);
                $queueIndex++;
            }
        }
    }

    /**
     * Attach a new request to the multi handle
     *
     * @param array<int, array{handle: CurlHandle, parser: HeaderParser, request: HttpRequest, index: int}> $active
     */
    private function attachRequest(
        CurlMultiHandle $multiHandle,
        HttpRequest $request,
        int $requestIndex,
        array &$active,
    ): void {
        $handle = $this->factory->createHandle($request);
        $parser = new HeaderParser();

        // Configure for sync (pooled requests are always sync)
        $handle->setOption(CURLOPT_RETURNTRANSFER, true);
        $handle->setOption(CURLOPT_HEADERFUNCTION, fn($_, $line) => $this->parseHeader($parser, $line));

        curl_multi_add_handle($multiHandle, $handle->native());

        $handleId = spl_object_id($handle->native());
        $active[$handleId] = [
            'handle' => $handle,
            'parser' => $parser,
            'request' => $request,
            'index' => $requestIndex,
        ];

        $this->dispatchRequestSent($request);
    }

    /**
     * Detach handle from multi and cleanup
     *
     * @param array<int, array{handle: CurlHandle, parser: HeaderParser, request: HttpRequest, index: int}> $active
     */
    private function detachHandle(
        CurlMultiHandle $multiHandle,
        CurlHandle $handle,
        array &$active,
    ): void {
        $handleId = spl_object_id($handle->native());
        curl_multi_remove_handle($multiHandle, $handle->native());
        unset($active[$handleId]);
        // CurlHandle destructor will close the handle
    }

    /**
     * Create response from completed handle
     *
     * Note: For pooled requests with curl_multi, we can't use curl_exec.
     * We must retrieve the body using curl_multi_getcontent after the transfer completes.
     * This is a limitation of curl_multi - the body is only available via curl_multi_getcontent.
     */
    private function createResponse(
        CurlHandle $handle,
        HeaderParser $parser,
        HttpRequest $request,
    ): HttpResponse {
        // Check for curl errors
        if ($handle->errorCode() !== 0) {
            $this->handleError($handle, $request);
        }

        // For pooled requests, we can't use SyncCurlResponse's internal execute()
        // because curl_exec won't work after curl_multi has driven the transfer
        // So we create a specialized pool response that accepts the body
        $adapter = new PoolCurlResponseAdapter(
            handle: $handle,
            headerParser: $parser,
        );
        $response = $adapter->toHttpResponse();

        $this->validateStatusCodeOrFail($response, $request);
        $this->dispatchResponseReceived($response->statusCode());

        return $response;
    }

    /**
     * Parse header callback
     */
    private function parseHeader(HeaderParser $parser, string $line): int {
        $parser->parse($line);
        return strlen($line);
    }

    /**
     * Handle curl errors
     */
    private function handleError(CurlHandle $handle, HttpRequest $request): never {
        $errorCode = $handle->errorCode();
        $errorMessage = $handle->error() ?? 'Unknown error';

        $exception = match (true) {
            in_array($errorCode, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED])
                => new TimeoutException($errorMessage, $request, null),
            in_array($errorCode, [
                CURLE_COULDNT_CONNECT,
                CURLE_COULDNT_RESOLVE_HOST,
                CURLE_COULDNT_RESOLVE_PROXY,
                CURLE_SSL_CONNECT_ERROR,
            ]) => new ConnectionException($errorMessage, $request, null),
            default => new NetworkException($errorMessage, $request, null, null),
        };

        $this->dispatchRequestFailed($exception, $request);
        throw $exception;
    }

    /**
     * Validate status code
     */
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

    /**
     * Finalize responses - sort by original order
     *
     * @param array<int, Result> $responses
     * @return array<Result>
     */
    private function finalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }

    // EVENT DISPATCHING /////////////////////////////////////////////

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
