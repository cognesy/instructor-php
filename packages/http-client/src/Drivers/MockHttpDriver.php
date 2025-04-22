<?php

namespace Cognesy\Http\Drivers;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;

/**
 * MockHttpDriver
 * 
 * A test double that implements CanHandleHttp for unit testing HTTP clients
 * without making actual HTTP requests.
 */
class MockHttpDriver implements CanHandleHttpRequest
{
    /** @var array Array of request matchers to predefined responses */
    private array $responses = [];
    
    /** @var HttpClientRequest[] Array of received requests for inspection */
    private array $receivedRequests = [];
    
    /** @var EventDispatcher|null Event dispatcher instance */
    private ?EventDispatcher $events;

    /**
     * Constructor
     * 
     * @param EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(?EventDispatcher $events = null)
    {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Handle an HTTP request by returning a predefined response
     * 
     * @param HttpClientRequest $request The request to handle
     * @return HttpClientResponse The predefined response
     * @throws InvalidArgumentException If no response is defined for the request
     */
    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        // Record the request for later inspection
        $this->receivedRequests[] = $request;
        
        // Search for a matching response based on defined matchers
        foreach ($this->responses as $matcher) {
            $matchesCriteria = true;
            
            // Check URL match if defined
            if (isset($matcher['url']) && $matcher['url'] !== null) {
                if (is_string($matcher['url']) && $matcher['url'] !== $request->url()) {
                    $matchesCriteria = false;
                } elseif (is_callable($matcher['url']) && !$matcher['url']($request->url())) {
                    $matchesCriteria = false;
                }
            }
            
            // Check method match if defined
            if (isset($matcher['method']) && $matcher['method'] !== null) {
                if ($matcher['method'] !== $request->method()) {
                    $matchesCriteria = false;
                }
            }
            
            // Check body match if defined
            if (isset($matcher['body']) && $matcher['body'] !== null) {
                $requestBody = $request->body()->toString();
                if (is_string($matcher['body']) && $matcher['body'] !== $requestBody) {
                    $matchesCriteria = false;
                } elseif (is_callable($matcher['body']) && !$matcher['body']($requestBody)) {
                    $matchesCriteria = false;
                }
            }
            
            // If all criteria match, return the response
            if ($matchesCriteria) {
                if (is_callable($matcher['response'])) {
                    return $matcher['response']($request);
                }
                return $matcher['response'];
            }
        }
        
        throw new InvalidArgumentException(
            "No response defined for request to {$request->method()} {$request->url()}"
        );
    }
    
    /**
     * Add a predefined response for a specific request pattern
     * 
     * @param HttpClientResponse|callable $response The response to return or a callback that returns a response
     * @param string|callable|null $url URL to match exactly or a callable that returns true if URL matches
     * @param string|null $method HTTP method to match
     * @param string|callable|null $body Body content to match exactly or a callable that returns true if body matches
     * @return self
     */
    public function addResponse(
        HttpClientResponse|callable $response,
        string|callable|null $url = null,
        ?string $method = null,
        string|callable|null $body = null
    ): self {
        $this->responses[] = [
            'url' => $url,
            'method' => $method,
            'body' => $body,
            'response' => $response
        ];
        
        return $this;
    }
    
    /**
     * Get all received requests for inspection
     * 
     * @return HttpClientRequest[]
     */
    public function getReceivedRequests(): array
    {
        return $this->receivedRequests;
    }
    
    /**
     * Get the last received request
     * 
     * @return \Cognesy\Http\Data\HttpClientRequest|null
     */
    public function getLastRequest(): ?HttpClientRequest
    {
        if (empty($this->receivedRequests)) {
            return null;
        }
        
        return $this->receivedRequests[count($this->receivedRequests) - 1];
    }
    
    /**
     * Reset the mock by clearing all received requests
     * 
     * @return self
     */
    public function reset(): self
    {
        $this->receivedRequests = [];
        return $this;
    }
    
    /**
     * Clear all predefined responses
     * 
     * @return self
     */
    public function clearResponses(): self
    {
        $this->responses = [];
        return $this;
    }
}
