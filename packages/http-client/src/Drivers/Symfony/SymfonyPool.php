<?php

namespace Cognesy\Http\Drivers\Symfony;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Utils\Result\Result;
use Exception;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyPool implements CanHandleRequestPool
{
    public function __construct(
        private HttpClientInterface $client,
        private HttpClientConfig $config,
        private EventDispatcherInterface $events,
    ) {}

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent;
        $responses = [];
        $httpResponses = $this->prepareHttpResponses($requests);
        try {
            $this->processHttpResponses($httpResponses, $responses, $maxConcurrent);
        } catch (Exception $e) {
            return $this->handlePoolException($e, $httpResponses);
        }

        return $this->normalizeResponses($responses);
    }

    private function prepareHttpResponses(array $requests): array {
        $httpResponses = [];
        foreach ($requests as $index => $request) {
            if (!$request instanceof HttpRequest) {
                throw new InvalidArgumentException('Invalid request type in pool');
            }
            $httpResponses[$index] = $this->prepareRequest($request);
        }
        return $httpResponses;
    }

    private function prepareRequest(HttpRequest $request) : ResponseInterface {
        try {
            $clientRequest = $this->client->request(
                method: $request->method(),
                url: $request->url(),
                options: [
                    'headers' => $request->headers(),
                    'body' => $request->body()->toString(),
                    'timeout' => $this->config->idleTimeout ?? 0,
                    'max_duration' => $this->config->requestTimeout ?? 30,
                    'buffer' => true,
                ]
            );
            $this->dispatchRequestEvent($request);
        } catch (Exception $e) {
            $this->handleRequestError($e, $request);
        }
        return $clientRequest;
    }

    private function processHttpResponses(array $httpResponses, array &$responses, int $maxConcurrent): void {
        try {
            foreach ($this->client->stream($httpResponses, $this->config->poolTimeout) as $response => $chunk) {
                try {
                    if ($chunk->isTimeout()) {
                        if ($this->config->failOnError) {
                            throw new HttpRequestException('Request timeout in pool');
                        }
                        $this->handleTimeout($response, $httpResponses, $responses);
                        continue;
                    }

                    if ($chunk->isLast()) {
                        $this->processLastChunk($response, $httpResponses, $responses);

                        if ($this->isPoolComplete($responses, count($httpResponses), $maxConcurrent)) {
                            break;
                        }
                    }
                } catch (TransportException $e) {
                    if ($this->config->failOnError) {
                        throw new HttpRequestException($e->getMessage());
                    }
                    $index = array_search($response, $httpResponses, true);
                    if ($index !== false) {
                        $responses[$index] = Result::failure(new HttpRequestException($e->getMessage()));
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->config->failOnError) {
                throw new HttpRequestException($e->getMessage());
            }
            // Handle any remaining unprocessed responses
            foreach ($httpResponses as $index => $response) {
                if (!isset($responses[$index])) {
                    $responses[$index] = Result::failure(new HttpRequestException($e->getMessage()));
                }
            }
        } finally {
            // Cancel any leftover/unconsumed responses
            foreach ($httpResponses as $resp) {
                try {
                    $resp->cancel();
                } catch (\Throwable $t) {
                    // swallow any cancel() error
                }
            }
        }
    }

    private function handleTimeout($response, array $httpResponses, array &$responses): void {
        $timeoutException = new HttpRequestException('Request timeout in pool');
        if ($this->config->failOnError) {
            throw $timeoutException;
        }
        $index = array_search($response, $httpResponses, true);
        if ($index !== false) {
            $responses[$index] = Result::failure($timeoutException);
        }
    }

    private function checkForErrors($response): ?HttpRequestException {
        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $responseInfo = $response->getInfo();
                $error = new HttpRequestException(sprintf(
                    'HTTP error %d: %s',
                    $statusCode,
                    $responseInfo['response_headers'][0] ?? 'Unknown error'
                ));
                if ($this->config->failOnError) {
                    throw $error;
                }
                return $error;
            }
            return null;
        } catch (TransportException $e) {
            $error = new HttpRequestException($e->getMessage());
            if ($this->config->failOnError) {
                throw $error;
            }
            return $error;
        }
    }

    private function processLastChunk($response, array $httpResponses, array &$responses): void {
        $index = array_search($response, $httpResponses, true);

        try {
            $statusCode = $response->getStatusCode();
            $error = $this->checkForErrors($response);

            if ($error !== null) {
                $responses[$index] = $this->handleError($error);
            } else {
                $responses[$index] = Result::success(new SymfonyHttpResponse(
                    client: $this->client,
                    response: $response,
                    connectTimeout: $this->config->connectTimeout ?? 3
                ));
            }

            $this->events->dispatch(new HttpResponseReceived($statusCode));
        } catch (Exception $e) {
            $responses[$index] = $this->handleError($e);
        }
    }

    private function handleError(Exception $error): Result {
        return match($this->config->failOnError) {
            true => throw new HttpRequestException($error),
            default => Result::failure($error),
        };
    }

    private function handlePoolException(Exception $e, array $httpResponses): array {
        if ($this->config->failOnError) {
            throw new HttpRequestException($e);
        }

        $responses = [];
        foreach ($httpResponses as $index => $_) {
            $responses[$index] = Result::failure($e);
        }
        return $responses;
    }

    private function isPoolComplete(array $responses, int $totalRequests, int $maxConcurrent): bool {
        return count($responses) >= min($totalRequests, $maxConcurrent);
    }

    private function dispatchRequestEvent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
        ]));
    }

    private function handleRequestError(Exception $e, HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $e->getMessage(),
        ]));

        if ($this->config->failOnError) {
            throw new HttpRequestException($e);
        }
    }

    private function normalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }
}