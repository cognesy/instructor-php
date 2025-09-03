<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Mock;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpResponseReceived;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * MockHttpDriver
 * 
 * A test double that implements CanHandleHttp for unit testing HTTP clients
 * without making actual HTTP requests.
 */
class MockHttpDriver implements CanHandleHttpRequest
{
    /** @var array Array of legacy request matchers to predefined responses */
    private array $responses = [];
    /** @var array<int,array{matchers: callable[], times: int|null, response: (callable|HttpResponse)}> */
    private array $expectations = [];
    
    /** @var HttpRequest[] Array of received requests for inspection */
    private array $receivedRequests = [];
    
    /** @var EventDispatcherInterface|null Event dispatcher instance */
    private ?EventDispatcherInterface $events;

    /**
     * Constructor
     * 
     * @param \Cognesy\Events\Dispatchers\EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(?EventDispatcherInterface $events = null)
    {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Handle an HTTP request by returning a predefined response
     * 
     * @param HttpRequest $request The request to handle
     * @return HttpResponse The predefined response
     * @throws InvalidArgumentException If no response is defined for the request
     */
    public function handle(HttpRequest $request): HttpResponse
    {
        // Record the request for later inspection
        $this->receivedRequests[] = $request;
        // If an event dispatcher is set, dispatch the request event
        if ($this->events) {
            $this->events->dispatch(new HttpResponseReceived($request));
        }
        
        // 1) Check new-style expectations first (fluent DSL)
        foreach ($this->expectations as $idx => $exp) {
            $matches = true;
            foreach ($exp['matchers'] as $m) {
                if (!$m($request)) { $matches = false; break; }
            }
            if ($matches) {
                // Decrement times if limited
                if ($exp['times'] !== null) {
                    $this->expectations[$idx]['times'] -= 1;
                    if ($this->expectations[$idx]['times'] <= 0) {
                        array_splice($this->expectations, $idx, 1);
                    }
                }
                $resp = $exp['response'];
                return is_callable($resp) ? $resp($request) : $resp;
            }
        }

        // 2) Fallback: Search legacy matchers for backward compatibility
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
            "No mock match for {$request->method()} {$request->url()}"
        );
    }
    
    /**
     * Add a predefined response for a specific request pattern
     * 
     * @param HttpResponse|callable $response The response to return or a callback that returns a response
     * @param string|callable|null $url URL to match exactly or a callable that returns true if URL matches
     * @param string|null $method HTTP method to match
     * @param string|callable|null $body Body content to match exactly or a callable that returns true if body matches
     * @return self
     */
    public function addResponse(
        HttpResponse|callable $response,
        string|callable|null  $url = null,
        ?string               $method = null,
        string|callable|null  $body = null
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
     * @return HttpRequest[]
     */
    public function getReceivedRequests(): array
    {
        return $this->receivedRequests;
    }
    
    /**
     * Get the last received request
     * 
     * @return \Cognesy\Http\Data\HttpRequest|null
     */
    public function getLastRequest(): ?HttpRequest
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
        $this->expectations = [];
        return $this;
    }

    /**
     * Begin a fluent expectation definition.
     */
    public function on(): MockExpectation { return new MockExpectation($this); }

    /**
     * Alias for on().
     */
    public function expect(): MockExpectation { return $this->on(); }

    /**
     * INTERNAL: Register a compiled expectation (used by MockExpectation::reply()).
     * @param array{matchers: callable[], times: int|null, response: (callable|HttpResponse)} $compiled
     */
    public function registerExpectation(array $compiled): self {
        $this->expectations[] = $compiled;
        return $this;
    }
}
