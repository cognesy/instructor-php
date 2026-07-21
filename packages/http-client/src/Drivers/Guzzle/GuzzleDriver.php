<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Guzzle;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Drivers\DispatchesHttpDriverEvents;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleDriver implements CanHandleHttpRequest
{
    use DispatchesHttpDriverEvents;

    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;
    protected ClientInterface $client;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        $this->config = $config;
        $this->events = $events;
        if ($clientInstance !== null && !($clientInstance instanceof ClientInterface)) {
            throw new \InvalidArgumentException('Client instance of GuzzleDriver must be of type GuzzleHttp\ClientInterface');
        }
        $this->client = $clientInstance ?? new Client();
    }

    #[\Override]
    public function handle(HttpRequest $request) : HttpResponse {
        $this->dispatchRequestSent($request);
        try {
            $rawResponse = $this->performHttpCall($request);
            $httpResponse = $this->buildHttpResponse($rawResponse, $request);
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e, $request);
        }

        if ($this->config->failOnError && $httpResponse->statusCode() >= 400) {
            $httpException = HttpExceptionFactory::fromStatusCode($httpResponse->statusCode(), $request, $httpResponse);
            $this->dispatchStatusCodeFailed($httpResponse->statusCode(), $request);
            throw $httpException;
        }
        $this->dispatchResponseReceived($request, $httpResponse->statusCode(), $httpResponse->isStreamed(), $httpResponse->isStreamed() ? null : $httpResponse->body());
        return $httpResponse;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function performHttpCall(HttpRequest $request) : ResponseInterface {
        $body = $request->body()->toString();
        $options = [
            'headers' => $request->headers(),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
            'stream' => $request->isStreamed(),
            'http_errors' => false, // Disable Guzzle's automatic HTTP error handling
            'verify' => $this->config->verifyTls,
            'allow_redirects' => $this->redirectOptions(),
        ];
        if ($this->config->httpVersion !== null) {
            $options['version'] = $this->config->httpVersion;
        }

        if ($body !== '') {
            $options['body'] = $body;
        }

        return $this->client->request($request->method(), $request->url(), $options);
    }

    private function redirectOptions(): bool|array {
        return match ($this->config->followRedirects) {
            true => $this->config->maxRedirects === null ? true : ['max' => $this->config->maxRedirects],
            false => false,
        };
    }

    private function buildHttpResponse(ResponseInterface $response, HttpRequest $request): HttpResponse {
        return (new PsrHttpResponseAdapter(
            response: $response,
            stream: $response->getBody(),
            events: $this->events,
            isStreamed: $request->isStreamed(),
            requestId: $request->id,
            streamChunkSize: $this->config->streamChunkSize,
        ))->toHttpResponse();
    }

    // exception handling

    private function handleGuzzleException(GuzzleException $e, HttpRequest $request): never {
        if ($e instanceof GuzzleRequestException && $e->getResponse() !== null) {
            $statusCode = $e->getResponse()->getStatusCode();
            $httpException = HttpExceptionFactory::fromStatusCode(
                statusCode: $statusCode,
                request: $request,
                previous: $e,
            );
            $this->dispatchStatusCodeFailed($statusCode, $request);
            throw $httpException;
        }

        $message = $e->getMessage();
        $httpException = match (true) {
            $e instanceof GuzzleConnectException && $this->isTimeout($e)
                => new TimeoutException($message, $request, null, $e),
            $e instanceof GuzzleConnectException
                => new ConnectionException($message, $request, null, $e),
            default
                => new NetworkException($message, $request, null, null, $e),
        };

        $this->dispatchRequestFailed($httpException, $request);
        throw $httpException;
    }

    private function isTimeout(GuzzleConnectException $exception): bool {
        $context = $exception->getHandlerContext();
        $errno = (int) ($context['errno'] ?? 0);

        return in_array($errno, [
            defined('CURLE_OPERATION_TIMEDOUT') ? CURLE_OPERATION_TIMEDOUT : 28,
            defined('CURLE_OPERATION_TIMEOUTED') ? CURLE_OPERATION_TIMEOUTED : 28,
        ], true)
            || ($context['timed_out'] ?? false) === true
            || str_contains(strtolower($exception->getMessage()), 'timed out');
    }

    // event dispatching





}
