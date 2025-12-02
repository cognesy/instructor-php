<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\ExtHttp;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\Result\Success;
use http\Client as ExtHttpClient;
use http\Client\Request as ExtHttpRequest;
use http\Client\Response as ExtHttpResponse;
use http\Exception\RuntimeException as ExtHttpRuntimeException;
use http\Message\Body as ExtHttpMessageBody;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * ExtHttpPool - Concurrent request handling using ext-http
 *
 * Leverages ext-http's built-in connection pooling and multiplexing capabilities
 * for efficient concurrent HTTP request processing.
 */
class ExtHttpPool implements CanHandleRequestPool
{
    private ExtHttpClient $client;
    private EventDispatcherInterface $events;

    public function __construct(
        protected HttpClientConfig $config,
        ?ExtHttpClient $client = null,
        ?EventDispatcherInterface $events = null,
    ) {
        if (!extension_loaded('http')) {
            throw new RuntimeException('ext-http extension is not loaded');
        }

        $this->client = $client ?? new ExtHttpClient();
        $this->events = $events ?? new EventDispatcher();
        $this->configureClient();
    }

    #[\Override]
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList
    {
        $concurrency = $maxConcurrent ?? $this->config->maxConcurrent;
        $responses = [];

        // Process requests in batches based on concurrency limit
        $requestBatches = $requests->chunk($concurrency);

        foreach ($requestBatches as $batch) {
            $batchResults = $this->processBatch($batch->all());
            $responses = array_merge($responses, $batchResults);
        }

        return HttpResponseList::from($responses);
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function configureClient(): void
    {
        // Configure client for optimal concurrent performance
        $this->client->configure([
            'connecttimeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'compress' => true,
            'cookiestore' => '', // Enable cookie handling
            // Enable persistent connections for better performance
            'persistent' => true,
        ]);
    }

    /**
     * Process a batch of requests concurrently
     *
     * @param HttpRequest[] $requests
     * @return Result[]
     */
    private function processBatch(array $requests): array
    {
        $extHttpRequests = [];
        $requestMap = [];

        // Create ext-http requests and map them to original requests
        foreach ($requests as $index => $request) {
            try {
                $extHttpRequest = $this->createExtHttpRequest($request);
                $extHttpRequests[] = $extHttpRequest;
                $requestMap[spl_object_id($extHttpRequest)] = ['index' => $index, 'original' => $request];

                $this->events->dispatch(new HttpRequestSent($request));
            } catch (\Throwable $e) {
                // If we can't create the request, add a failure result
                $extHttpRequests[] = null;
                $requestMap[$index] = ['index' => $index, 'original' => $request, 'error' => $e];
            }
        }

        // Enqueue all requests
        foreach ($extHttpRequests as $extHttpRequest) {
            if ($extHttpRequest !== null) {
                $this->client->enqueue($extHttpRequest);
            }
        }

        // Send all requests concurrently
        try {
            $this->client->send();
        } catch (ExtHttpRuntimeException $e) {
            // If sending fails, create failure results for all requests
            return $this->createFailureResults($requests, $e);
        }

        // Collect responses
        $results = array_fill(0, count($requests), null);

        foreach ($extHttpRequests as $extHttpRequest) {
            if ($extHttpRequest === null) {
                continue;
            }

            $requestId = spl_object_id($extHttpRequest);
            $requestInfo = $requestMap[$requestId];
            $originalRequest = $requestInfo['original'];
            $index = $requestInfo['index'];

            try {
                $extHttpResponse = $this->client->getResponse($extHttpRequest);

                if (!$extHttpResponse instanceof ExtHttpResponse) {
                    throw new NetworkException('Failed to receive response from ext-http client', $originalRequest);
                }

                $httpResponse = $this->buildHttpResponse($extHttpResponse, $originalRequest);

                // Check for HTTP errors if configured
                if ($this->config->failOnError && $httpResponse->statusCode() >= 400) {
                    $httpException = HttpExceptionFactory::fromStatusCode(
                        $httpResponse->statusCode(),
                        $originalRequest,
                        $httpResponse
                    );
                    $results[$index] = new Failure($httpException);
                } else {
                    $this->events->dispatch(new HttpResponseReceived($httpResponse));
                    $results[$index] = new Success($httpResponse);
                }

            } catch (\Throwable $e) {
                $results[$index] = new Failure($e);
            }
        }

        // Fill any remaining null slots with failures for requests that had creation errors
        foreach ($results as $index => $result) {
            if ($result === null) {
                $request = $requests[$index];
                $error = $requestMap[$index]['error'] ?? new NetworkException('Unknown error processing request', $request);
                $results[$index] = new Failure($error);
            }
        }

        return $results;
    }

    private function createExtHttpRequest(HttpRequest $request): ExtHttpRequest
    {
        $extHttpRequest = new ExtHttpRequest(
            $request->method(),
            $request->url(),
            $request->headers()
        );

        // Set request body if present
        $bodyString = $request->body()->toString();
        if (!empty($bodyString)) {
            $messageBody = new ExtHttpMessageBody();
            $messageBody->append($bodyString);
            $extHttpRequest->setBody($messageBody);

            // Determine content type based on body content
            $bodyArray = $request->body()->toArray();
            if (!empty($bodyArray)) {
                // Body was originally an array (JSON)
                $extHttpRequest->setContentType('application/json');
            } else {
                // Body is string - set content type based on headers or default
                $contentType = $request->headers('Content-Type');
                if (!empty($contentType)) {
                    $extHttpRequest->setContentType(is_array($contentType) ? $contentType[0] : $contentType);
                }
            }
        }

        return $extHttpRequest;
    }

    private function buildHttpResponse(ExtHttpResponse $response, HttpRequest $request): \Cognesy\Http\Data\HttpResponse
    {
        return (new ExtHttpResponseAdapter(
            response: $response,
            events: $this->events,
            isStreamed: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize ?? 256,
        ))->toHttpResponse();
    }

    /**
     * Create failure results for all requests when batch sending fails
     *
     * @param HttpRequest[] $requests
     * @param \Throwable $exception
     * @return Result[]
     */
    private function createFailureResults(array $requests, \Throwable $exception): array
    {
        $results = [];
        foreach ($requests as $request) {
            $networkException = new NetworkException($exception->getMessage(), $request, $exception);
            $results[] = new Failure($networkException);
        }
        return $results;
    }
}