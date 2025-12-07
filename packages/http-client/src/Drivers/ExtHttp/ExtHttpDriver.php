<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\ExtHttp;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
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
use http\Client as ExtHttpClient;
use http\Client\Request as ExtHttpRequest;
use http\Client\Response as ExtHttpResponse;
use http\Exception\InvalidArgumentException as ExtHttpInvalidArgumentException;
use http\Exception\RuntimeException as ExtHttpRuntimeException;
use http\Message\Body as ExtHttpMessageBody;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * ExtHttpDriver - HTTP Driver based on ext-http (pecl_http)
 *
 * Uses the native ext-http extension for high-performance HTTP client operations.
 * Provides both synchronous and asynchronous request handling with connection pooling.
 *
 * Features:
 * - Native C-level performance
 * - Built-in connection pooling and persistent connections
 * - Advanced request/response handling
 * - Automatic compression support
 * - Cookie management
 */
final class ExtHttpDriver implements CanHandleHttpRequest
{
    private readonly ExtHttpClient $client;

    public function __construct(
        private readonly HttpClientConfig $config,
        private readonly EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        if (!extension_loaded('http')) {
            throw new RuntimeException('ext-http extension is not loaded');
        }

        if ($clientInstance !== null && !($clientInstance instanceof ExtHttpClient)) {
            throw new InvalidArgumentException('Client instance must be of type http\Client');
        }

        $this->client = $clientInstance ?? new ExtHttpClient();
        $this->configureClient();
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse
    {
        $this->dispatchRequestSent($request);

        try {
            $extHttpRequest = $this->createExtHttpRequest($request);
            $this->client->enqueue($extHttpRequest);
            $this->client->send();

            $extHttpResponse = $this->client->getResponse($extHttpRequest);

            if (!$extHttpResponse instanceof ExtHttpResponse) {
                throw new NetworkException('Failed to receive response from ext-http client', $request);
            }

            $httpResponse = $this->buildHttpResponse($extHttpResponse, $request);

        } catch (ExtHttpRuntimeException $e) {
            $this->handleExtHttpException($e, $request);
        } catch (ExtHttpInvalidArgumentException $e) {
            $this->handleInvalidArgumentException($e, $request);
        }

        if ($this->config->failOnError && $httpResponse->statusCode() >= 400) {
            $httpException = HttpExceptionFactory::fromStatusCode($httpResponse->statusCode(), $request, $httpResponse);
            $this->dispatchStatusCodeFailed($httpResponse->statusCode(), $request);
            throw $httpException;
        }

        $this->dispatchResponseReceived($httpResponse);
        return $httpResponse;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function configureClient(): void
    {
        // Configure client options based on HttpClientConfig
        if ($this->config->connectTimeout > 0) {
            $this->client->configure([
                'connecttimeout' => $this->config->connectTimeout,
            ]);
        }

        if ($this->config->requestTimeout > 0) {
            $this->client->configure([
                'timeout' => $this->config->requestTimeout,
            ]);
        }

        // Enable compression by default
        $this->client->configure([
            'compress' => true,
            'cookiestore' => '', // Enable automatic cookie handling
        ]);
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

    private function buildHttpResponse(ExtHttpResponse $response, HttpRequest $request): HttpResponse
    {
        return (new ExtHttpResponseAdapter(
            response: $response,
            events: $this->events,
            isStreamed: $request->isStreamed(),
            streamChunkSize: $this->config->streamChunkSize ?? 256,
        ))->toHttpResponse();
    }

    private function handleExtHttpException(ExtHttpRuntimeException $e, HttpRequest $request): never
    {
        $this->dispatchRequestFailed($request);

        // Map ext-http exceptions to our exception types
        $message = $e->getMessage();

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            throw new TimeoutException($message, $request, null, $e);
        }

        if (str_contains($message, 'connect') || str_contains($message, 'connection')) {
            throw new ConnectionException($message, $request, null, $e);
        }

        throw new NetworkException($message, $request, null, null, $e);
    }

    private function handleInvalidArgumentException(ExtHttpInvalidArgumentException $e, HttpRequest $request): never
    {
        $this->dispatchRequestFailed($request);
        throw new HttpRequestException($e->getMessage(), $request, null, null, $e);
    }

    // EVENT DISPATCHING /////////////////////////////////////////////////////////////////

    private function dispatchRequestSent(HttpRequest $request): void
    {
        $this->events->dispatch(new HttpRequestSent($request));
    }

    private function dispatchResponseReceived(HttpResponse $response): void
    {
        $this->events->dispatch(new HttpResponseReceived($response));
    }

    private function dispatchRequestFailed(HttpRequest $request): void
    {
        $this->events->dispatch(new HttpRequestFailed($request));
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request): void
    {
        $this->events->dispatch(new HttpRequestFailed(['request' => $request, 'message' => "HTTP {$statusCode}"]));
    }
}