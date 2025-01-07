<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\SymfonyResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyDriver implements CanHandleHttp
{
    private HttpClientInterface $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpClientInterface $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->client = $httpClient ?? HttpClient::create();
    }

    public function handle(HttpClientRequest $request) : CanAccessResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
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
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));
        return new SymfonyResponse(
            client: $this->client,
            response: $response,
            connectTimeout: $this->config->connectTimeout ?? 3,
        );
    }

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        // Use config maxConcurrent if not provided
        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent;

        // Validate requests and prepare responses array
        $responses = [];
        $httpResponses = [];

        // Create array of responses for streaming
        foreach ($requests as $index => $request) {
            if (!$request instanceof HttpClientRequest) {
                throw new Exception('Invalid request type in pool');
            }

            try {
                // Prepare the request options
                $options = [
                    'headers' => $request->headers(),
                    'body' => is_array($request->body()) ? json_encode($request->body()) : $request->body(),
                    'timeout' => $this->config->idleTimeout ?? 0,
                    'max_duration' => $this->config->requestTimeout ?? 30,
                    'buffer' => true, // We don't want streaming for pooled requests
                ];

                // Create the request
                $httpResponses[$index] = $this->client->request(
                    method: $request->method(),
                    url: $request->url(),
                    options: $options
                );

                // Dispatch event for request
                $this->events->dispatch(new RequestSentToLLM(
                    $request->url(),
                    $request->method(),
                    $request->headers(),
                    $request->body()
                ));

            } catch (Exception $e) {
                $this->events->dispatch(new RequestToLLMFailed(
                    $request->url(),
                    $request->method(),
                    $request->headers(),
                    $request->body(),
                    $e->getMessage()
                ));
                if ($this->config->failOnError) {
                    throw $e;
                }
                // Skip failed request if not failing on error
                continue;
            }
        }

        // Process responses using Symfony's stream
        foreach ($this->client->stream($httpResponses, $this->config->poolTimeout) as $response => $chunk) {
            if ($chunk->isTimeout()) {
                // Handle timeout - skip or throw based on config
                if ($this->config->failOnError) {
                    throw new Exception('Request timeout in pool');
                }
                continue;
            }

            if ($chunk->isLast()) {
                // Find the index of this response
                $index = array_search($response, $httpResponses, true);

                // Create our response adapter
                $responses[$index] = new SymfonyResponse(
                    client: $this->client,
                    response: $response,
                    connectTimeout: $this->config->connectTimeout ?? 3
                );

                // Dispatch event for successful response
                $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));

                // If we've got all responses, we can break
                if (count($responses) >= min(count($requests), $maxConcurrent)) {
                    break;
                }
            }
        }

        // Sort responses by original request order
        ksort($responses);

        return array_values($responses);
    }
}
