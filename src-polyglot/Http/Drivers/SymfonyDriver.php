<?php

namespace Cognesy\Polyglot\Http\Drivers;

use Cognesy\Polyglot\Http\Adapters\SymfonyResponseAdapter;
use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\ResponseAdapter;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Events\HttpRequestFailed;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Polyglot\Http\Exceptions\RequestException;
use Cognesy\Utils\Debug\Debug;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Result\Result;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyDriver implements CanHandleHttp
{
    private HttpClientInterface $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpClientInterface $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->client = $httpClient ?? HttpClient::create(['http_version' => '2.0']);
    }

    public function handle(HttpClientRequest $request) : ResponseAdapter {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new HttpRequestSent($url, $method, $headers, $body));
        try {
            Debug::tryDumpUrl($url);
            $response = $this->client->request(
                method: $method,
                url: $url,
                options: [
                    'headers' => $headers,
                    'body' => is_array($body) ? json_encode($body) : $body,
                    'timeout' => $this->config->idleTimeout ?? 0,
                    'max_duration' => $this->config->requestTimeout ?? 30,
                    'buffer' => !$streaming,
                ]
            );
        } catch (Exception $e) {
            $this->events->dispatch(new HttpRequestFailed($url, $method, $headers, $body, $e->getMessage()));
            throw new RequestException($e);
        }
        $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));
        return new SymfonyResponseAdapter(
            client: $this->client,
            response: $response,
            connectTimeout: $this->config->connectTimeout ?? 3,
        );
    }

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

    // INTERNAL /////////////////////////////////////////////////

    private function prepareHttpResponses(array $requests): array {
        $httpResponses = [];
        foreach ($requests as $index => $request) {
            if (!$request instanceof HttpClientRequest) {
                throw new InvalidArgumentException('Invalid request type in pool');
            }
            $httpResponses[$index] = $this->prepareRequest($request);
        }
        return $httpResponses;
    }

    private function prepareRequest(HttpClientRequest $request) : ResponseInterface {
        try {
            $clientRequest = $this->client->request(
                method: $request->method(),
                url: $request->url(),
                options: [
                    'headers' => $request->headers(),
                    'body' => is_array($request->body()) ? json_encode($request->body()) : $request->body(),
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
                            throw new RequestException('Request timeout in pool');
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
                        throw new RequestException($e->getMessage());
                    }
                    $index = array_search($response, $httpResponses, true);
                    if ($index !== false) {
                        $responses[$index] = Result::failure(new RequestException($e->getMessage()));
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->config->failOnError) {
                throw new RequestException($e->getMessage());
            }
            // Handle any remaining unprocessed responses
            foreach ($httpResponses as $index => $response) {
                if (!isset($responses[$index])) {
                    $responses[$index] = Result::failure(new RequestException($e->getMessage()));
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
        $timeoutException = new RequestException('Request timeout in pool');
        if ($this->config->failOnError) {
            throw $timeoutException;
        }
        $index = array_search($response, $httpResponses, true);
        if ($index !== false) {
            $responses[$index] = Result::failure($timeoutException);
        }
    }

    private function checkForErrors($response): ?RequestException {
        try {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $responseInfo = $response->getInfo();
                $error = new RequestException(sprintf(
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
            $error = new RequestException($e->getMessage());
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
                $responses[$index] = Result::success(new SymfonyResponseAdapter(
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
            true => throw new RequestException($error),
            default => Result::failure($error),
        };
    }

    private function handlePoolException(Exception $e, array $httpResponses): array {
        if ($this->config->failOnError) {
            throw new RequestException($e);
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

    private function dispatchRequestEvent(HttpClientRequest $request): void {
        $this->events->dispatch(new HttpRequestSent(
            $request->url(),
            $request->method(),
            $request->headers(),
            $request->body()
        ));
    }

    private function handleRequestError(Exception $e, HttpClientRequest $request): void {
        $this->events->dispatch(new HttpRequestFailed(
            $request->url(),
            $request->method(),
            $request->headers(),
            $request->body(),
            $e->getMessage()
        ));

        if ($this->config->failOnError) {
            throw new RequestException($e);
        }
    }

    private function normalizeResponses(array $responses): array {
        ksort($responses);
        return array_values($responses);
    }
}
