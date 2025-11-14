<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * Pool handler for concurrent HTTP requests using curl_multi
 */
class CurlPool implements CanHandleRequestPool
{
    private HttpClientConfig $config;
    private EventDispatcherInterface $events;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
    ) {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is not loaded');
        }

        $this->config = $config;
        $this->events = $events;
    }

    /**
     * Handle a pool of HTTP requests concurrently using curl_multi
     *
     * @param HttpRequestList $requests
     * @param int|null $maxConcurrent
     * @return HttpResponseList Collection of Result objects (Success or Failure)
     */
    #[\Override]
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        if ($requests->isEmpty()) {
            return HttpResponseList::empty();
        }

        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent ?? 5;

        $responses = [];
        $handles = [];
        $requestMap = [];
        $requestQueue = array_values($requests->all());
        $queueIndex = 0;

        $multiHandle = $this->initMultiHandle();
        $activeRequests = $this->initializeFirstBatch(
            multiHandle: $multiHandle,
            requestQueue: $requestQueue,
            queueIndex: $queueIndex,
            maxConcurrent: $maxConcurrent,
            handles: $handles,
            requestMap: $requestMap,
        );

        $this->runEventLoop(
            multiHandle: $multiHandle,
            responses: $responses,
            handles: $handles,
            requestMap: $requestMap,
            requestQueue: $requestQueue,
            queueIndex: $queueIndex,
            activeRequests: $activeRequests,
            maxConcurrent: $maxConcurrent,
        );

        $this->cleanupMultiHandle($multiHandle);

        return $this->finalizeResponses($responses);
    }

    // INTERNAL /////////////////////////////////////////////

    private function createCurlHandle(HttpRequest $request): \CurlHandle {
        $curl = curl_init();

        // Configure curl handle (similar to CurlDriver)
        curl_setopt($curl, CURLOPT_URL, $request->url());
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($request->method()));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Set headers
        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[] = is_array($value)
                ? "{$name}: " . implode(', ', $value)
                : "{$name}: {$value}";
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Set body
        $body = $request->body()->toString();
        if (!empty($body)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        // Timeouts
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->config->connectTimeout ?? 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config->requestTimeout ?? 30);

        // SSL
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        // HTTP version
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        // Redirects
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);

        // Capture headers
        $responseHeaders = [];
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$responseHeaders) {
            $length = strlen($header);
            $header = trim($header);

            if (empty($header) || str_starts_with($header, 'HTTP/')) {
                return $length;
            }

            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                if (!isset($responseHeaders[$name])) {
                    $responseHeaders[$name] = [];
                }
                $responseHeaders[$name][] = $value;
            }

            return $length;
        });

        // Store headers reference for later retrieval
        curl_setopt($curl, CURLOPT_PRIVATE, serialize($responseHeaders));

        return $curl;
    }

    private function processCompletedHandle(\CurlHandle $handle, HttpRequest $request): HttpResponse {

        // Check for curl errors
        $errno = curl_errno($handle);
        if ($errno !== 0) {
            $error = curl_error($handle);
            $this->dispatchRequestFailed($error, $request);
            throw new NetworkException($error, $request, null, null);
        }

        // Get response data
        $body = curl_multi_getcontent($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        // Retrieve headers (this won't work as expected with curl_multi, headers callback is not accessible)
        // For simplicity, we'll use an empty array or extract from curl_getinfo
        $headers = $this->extractHeadersFromInfo($handle);

        // Create response object
        $response = (new CurlHttpResponseAdapter(
            statusCode: $statusCode,
            headers: $headers,
            body: $body ?: '',
            isStreamed: false,
            events: $this->events,
            streamChunkSize: $this->config->streamChunkSize ?? 256,
        ))->toHttpResponse();

        // Check if status indicates failure
        if ($statusCode >= 400) {
            $exception = HttpExceptionFactory::fromStatusCode(
                $statusCode,
                $request,
                $response,
            );

            $this->dispatchRequestFailed($exception->getMessage(), $request);

            // Always throw so it gets wrapped in Result::failure
            // The caller will decide whether to re-throw based on failOnError
            throw $exception;
        }

        $this->dispatchResponseReceived($statusCode);
        return $response;
    }

    // MODULARIZED INTERNALS /////////////////////////////////////////////

    /**
     * @return \CurlMultiHandle
     */
    private function initMultiHandle(): \CurlMultiHandle {
        /** @var \CurlMultiHandle $multi */
        $multi = curl_multi_init();
        return $multi;
    }

    /**
     * Initialize the first batch up to max concurrency.
     *
     * @param array<HttpRequest> $requestQueue
     * @param array<int, \CurlHandle> $handles
     * @param array<int, array{request: HttpRequest, index: int}> $requestMap
     */
    private function initializeFirstBatch(
        \CurlMultiHandle $multiHandle,
        array $requestQueue,
        int &$queueIndex,
        int $maxConcurrent,
        array &$handles,
        array &$requestMap,
    ): int {
        $activeRequests = 0;

        while ($queueIndex < count($requestQueue) && $activeRequests < $maxConcurrent) {
            $request = $requestQueue[$queueIndex];
            $this->attachRequest(
                multiHandle: $multiHandle,
                request: $request,
                requestIndex: $queueIndex,
                handles: $handles,
                requestMap: $requestMap,
            );
            $activeRequests++;
            $queueIndex++;
        }

        return $activeRequests;
    }

    /**
     * Main event loop running curl_multi and processing finished handles.
     *
     * @param array<int, Result> $responses
     * @param array<int, \CurlHandle> $handles
     * @param array<int, array{request: HttpRequest, index: int}> $requestMap
     * @param array<HttpRequest> $requestQueue
     */
    private function runEventLoop(
        \CurlMultiHandle $multiHandle,
        array &$responses,
        array &$handles,
        array &$requestMap,
        array $requestQueue,
        int &$queueIndex,
        int &$activeRequests,
        int $maxConcurrent,
    ): void {
        do {
            $status = curl_multi_exec($multiHandle, $stillRunning);
            if ($status !== CURLM_OK) {
                break;
            }

            $this->drainCompleted(
                multiHandle: $multiHandle,
                responses: $responses,
                handles: $handles,
                requestMap: $requestMap,
                requestQueue: $requestQueue,
                queueIndex: $queueIndex,
                activeRequests: $activeRequests,
                maxConcurrent: $maxConcurrent,
            );

            if ($stillRunning) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($stillRunning > 0);
    }

    /**
     * Read finished transfers and process their results, then enqueue next requests.
     *
     * @param array<int, Result> $responses
     * @param array<int, \CurlHandle> $handles
     * @param array<int, array{request: HttpRequest, index: int}> $requestMap
     * @param array<HttpRequest> $requestQueue
     */
    private function drainCompleted(
        \CurlMultiHandle $multiHandle,
        array &$responses,
        array &$handles,
        array &$requestMap,
        array $requestQueue,
        int &$queueIndex,
        int &$activeRequests,
        int $maxConcurrent,
    ): void {
        while ($info = curl_multi_info_read($multiHandle)) {
            if ($info['msg'] !== CURLMSG_DONE) {
                continue;
            }

            /** @var \CurlHandle $handle */
            $handle = $info['handle'];
            $handleId = spl_object_id($handle);
            $requestData = $requestMap[$handleId];

            try {
                $response = $this->processCompletedHandle(
                    $handle,
                    $requestData['request'],
                );
                $responses[$requestData['index']] = Result::success($response);
            } catch (\Throwable $e) {
                if ($this->config->failOnError) {
                    throw $e;
                }
                $responses[$requestData['index']] = Result::failure($e);
            }

            $this->detachHandle($multiHandle, $handle, $handles, $requestMap);
            $activeRequests--;

            if ($queueIndex < count($requestQueue) && $activeRequests < $maxConcurrent) {
                $nextRequest = $requestQueue[$queueIndex];
                $this->attachRequest(
                    multiHandle: $multiHandle,
                    request: $nextRequest,
                    requestIndex: $queueIndex,
                    handles: $handles,
                    requestMap: $requestMap,
                );
                $activeRequests++;
                $queueIndex++;
            }
        }
    }

    /**
     * Attach a request to the multi handle and update bookkeeping.
     *
     * @param array<int, \CurlHandle> $handles
     * @param array<int, array{request: HttpRequest, index: int}> $requestMap
     */
    private function attachRequest(
        \CurlMultiHandle $multiHandle,
        HttpRequest $request,
        int $requestIndex,
        array &$handles,
        array &$requestMap,
    ): void {
        $handle = $this->createCurlHandle($request);
        curl_multi_add_handle($multiHandle, $handle);

        $handleId = spl_object_id($handle);
        $handles[$handleId] = $handle;
        $requestMap[$handleId] = [
            'request' => $request,
            'index' => $requestIndex,
        ];

        $this->dispatchRequestSent($request);
    }

    /**
     * Remove handle from multi and local bookkeeping.
     *
     * @param array<int, \CurlHandle> $handles
     * @param array<int, array{request: HttpRequest, index: int}> $requestMap
     */
    private function detachHandle(
        \CurlMultiHandle $multiHandle,
        \CurlHandle $handle,
        array &$handles,
        array &$requestMap,
    ): void {
        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);
        $handleId = spl_object_id($handle);
        unset($handles[$handleId], $requestMap[$handleId]);
    }

    /**
     * Close multi handle.
     */
    private function cleanupMultiHandle(\CurlMultiHandle $multiHandle): void {
        curl_multi_close($multiHandle);
    }

    /**
     * Normalize and sort responses by original order.
     *
     * @param array<int, Result> $responses
     * @return HttpResponseList
     */
    private function finalizeResponses(array $responses): HttpResponseList {
        ksort($responses);
        return HttpResponseList::fromArray(array_values($responses));
    }

    private function extractHeadersFromInfo(\CurlHandle $handle): array {
        $headers = [];
        $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        if ($contentType) {
            $headers['Content-Type'] = [$contentType];
        }
        return $headers;
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
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $statusCode
        ]));
    }

    private function dispatchRequestFailed(string $error, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'errors' => $error,
        ]));
    }
}
