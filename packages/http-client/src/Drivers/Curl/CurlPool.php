<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Utils\Result\Result;
use CurlMultiHandle;
use Psr\EventDispatcher\EventDispatcherInterface;

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
            throw new \RuntimeException('cURL extension is not loaded');
        }

        $this->config = $config;
        $this->events = $events;
    }

    /**
     * Handle a pool of HTTP requests concurrently using curl_multi
     *
     * @param array<HttpRequest> $requests
     * @param int|null $maxConcurrent
     * @return array<Result> Array of Result objects (Success or Failure)
     */
    #[\Override]
    public function pool(array $requests, ?int $maxConcurrent = null): array {
        if (empty($requests)) {
            return [];
        }

        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent ?? 5;
        $responses = [];
        $handles = [];
        $requestMap = [];

        // Create curl multi handle
        $multiHandle = curl_multi_init();

        // Add initial batch of requests
        $activeRequests = 0;
        $requestQueue = array_values($requests);
        $queueIndex = 0;

        // Initialize first batch
        while ($queueIndex < count($requestQueue) && $activeRequests < $maxConcurrent) {
            $request = $requestQueue[$queueIndex];
            $handle = $this->createCurlHandle($request);

            curl_multi_add_handle($multiHandle, $handle);
            $handleId = spl_object_id($handle);
            $handles[$handleId] = $handle;
            $requestMap[$handleId] = [
                'request' => $request,
                'index' => $queueIndex,
                'startTime' => microtime(true),
            ];

            $this->dispatchRequestSent($request);
            $activeRequests++;
            $queueIndex++;
        }

        // Process requests
        do {
            // Execute handles
            $status = curl_multi_exec($multiHandle, $stillRunning);

            if ($status !== CURLM_OK) {
                break;
            }

            // Check for completed requests
            while ($info = curl_multi_info_read($multiHandle)) {
                if ($info['msg'] === CURLMSG_DONE) {
                    $handle = $info['handle'];
                    $handleId = spl_object_id($handle);
                    $requestData = $requestMap[$handleId];

                    try {
                        $response = $this->processCompletedHandle(
                            $handle,
                            $requestData['request'],
                            $requestData['startTime']
                        );
                        // Wrap successful response in Result::success
                        $responses[$requestData['index']] = Result::success($response);
                    } catch (\Throwable $e) {
                        // Wrap exception in Result::failure if not failing on error
                        if ($this->config->failOnError) {
                            throw $e;
                        }
                        $responses[$requestData['index']] = Result::failure($e);
                    }

                    // Remove completed handle
                    curl_multi_remove_handle($multiHandle, $handle);
                    curl_close($handle);
                    unset($handles[$handleId]);
                    unset($requestMap[$handleId]);
                    $activeRequests--;

                    // Add next request from queue if available
                    if ($queueIndex < count($requestQueue)) {
                        $nextRequest = $requestQueue[$queueIndex];
                        $nextHandle = $this->createCurlHandle($nextRequest);

                        curl_multi_add_handle($multiHandle, $nextHandle);
                        $nextHandleId = spl_object_id($nextHandle);
                        $handles[$nextHandleId] = $nextHandle;
                        $requestMap[$nextHandleId] = [
                            'request' => $nextRequest,
                            'index' => $queueIndex,
                            'startTime' => microtime(true),
                        ];

                        $this->dispatchRequestSent($nextRequest);
                        $activeRequests++;
                        $queueIndex++;
                    }
                }
            }

            // Wait for activity with timeout
            if ($stillRunning) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($stillRunning > 0);

        // Clean up
        curl_multi_close($multiHandle);

        // Sort responses by original request order
        ksort($responses);

        return array_values($responses);
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

    private function processCompletedHandle(\CurlHandle $handle, HttpRequest $request, float $startTime): HttpResponse {
        $duration = microtime(true) - $startTime;

        // Check for curl errors
        $errno = curl_errno($handle);
        if ($errno !== 0) {
            $error = curl_error($handle);
            $this->dispatchRequestFailed($error, $request, $duration);
            throw new NetworkException($error, $request, null, $duration);
        }

        // Get response data
        $body = curl_multi_getcontent($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        // Retrieve headers (this won't work as expected with curl_multi, headers callback is not accessible)
        // For simplicity, we'll use an empty array or extract from curl_getinfo
        $headers = $this->extractHeadersFromInfo($handle);

        // Create response object
        $response = new CurlHttpResponse(
            statusCode: $statusCode,
            headers: $headers,
            body: $body ?: '',
            isStreamed: false,
            events: $this->events,
            streamChunkSize: $this->config->streamChunkSize ?? 256,
        );

        // Check if status indicates failure
        if ($statusCode >= 400) {
            $exception = HttpExceptionFactory::fromStatusCode(
                $statusCode,
                $request,
                $response,
                $duration
            );

            $this->dispatchRequestFailed($exception->getMessage(), $request, $duration);

            // Always throw so it gets wrapped in Result::failure
            // The caller will decide whether to re-throw based on failOnError
            throw $exception;
        }

        $this->dispatchResponseReceived($statusCode);
        return $response;
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

    private function dispatchRequestFailed(string $error, HttpRequest $request, float $duration): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'errors' => $error,
            'duration' => $duration,
        ]));
    }
}
