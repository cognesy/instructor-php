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
     * @param EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(?EventDispatcherInterface $events = null) {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Handle an HTTP request by returning a predefined response
     *
     * @param HttpRequest $request The request to handle
     * @return HttpResponse The predefined response
     * @throws InvalidArgumentException If no response is defined for the request
     */
    public function handle(HttpRequest $request): HttpResponse {
        $this->recordRequest($request);
        $this->dispatchResponseReceived($request);

        $response = $this->findMatchingResponse($request);
        if ($response === null) {
            $this->throwNoMatchException($request);
        }

        return $response;
    }

    // INTERNAL /////////////////////////////////////////////

    private function recordRequest(HttpRequest $request): void {
        $this->receivedRequests[] = $request;
    }

    private function dispatchResponseReceived(HttpRequest $request): void {
        if ($this->events) {
            $this->events->dispatch(new HttpResponseReceived($request));
        }
    }

    private function findMatchingResponse(HttpRequest $request): ?HttpResponse {
        // 1) Check new-style expectations first (fluent DSL)
        $response = $this->findExpectationMatch($request);
        if ($response !== null) {
            return $response;
        }

        // 2) Fallback: Search legacy matchers for backward compatibility
        return $this->findLegacyMatch($request);
    }

    private function findExpectationMatch(HttpRequest $request): ?HttpResponse {
        foreach ($this->expectations as $idx => $exp) {
            if (!$this->matchesAllExpectationMatchers($exp['matchers'], $request)) {
                continue;
            }

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

        return null;
    }

    private function matchesAllExpectationMatchers(array $matchers, HttpRequest $request): bool {
        foreach ($matchers as $matcher) {
            if (!$matcher($request)) {
                return false;
            }
        }
        return true;
    }

    private function findLegacyMatch(HttpRequest $request): ?HttpResponse {
        foreach ($this->responses as $matcher) {
            if ($this->matchesLegacyCriteria($matcher, $request)) {
                $response = $matcher['response'];
                return is_callable($response) ? $response($request) : $response;
            }
        }
        return null;
    }

    private function matchesLegacyCriteria(array $matcher, HttpRequest $request): bool {
        // Check URL match if defined
        if (isset($matcher['url']) && $matcher['url'] !== null) {
            if (!$this->matchesUrlCriteria($matcher['url'], $request->url())) {
                return false;
            }
        }

        // Check method match if defined
        if (isset($matcher['method']) && $matcher['method'] !== null) {
            if ($matcher['method'] !== $request->method()) {
                return false;
            }
        }

        // Check body match if defined
        if (isset($matcher['body']) && $matcher['body'] !== null) {
            if (!$this->matchesBodyCriteria($matcher['body'], $request->body()->toString())) {
                return false;
            }
        }

        return true;
    }

    private function matchesUrlCriteria(string|callable $urlCriteria, string $requestUrl): bool {
        return match (true) {
            is_string($urlCriteria) => $urlCriteria === $requestUrl,
            is_callable($urlCriteria) => (bool)$urlCriteria($requestUrl),
            default => false,
        };
    }

    private function matchesBodyCriteria(string|callable $bodyCriteria, string $requestBody): bool {
        return match (true) {
            is_string($bodyCriteria) => $bodyCriteria === $requestBody,
            is_callable($bodyCriteria) => (bool)$bodyCriteria($requestBody),
            default => false,
        };
    }

    private function throwNoMatchException(HttpRequest $request): never {
        throw new InvalidArgumentException(
            "No mock match for {$request->method()} {$request->url()}",
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
        string|callable|null $url = null,
        ?string $method = null,
        string|callable|null $body = null,
    ): self {
        $this->responses[] = [
            'url' => $url,
            'method' => $method,
            'body' => $body,
            'response' => $response,
        ];

        return $this;
    }

    /**
     * Get all received requests for inspection
     *
     * @return HttpRequest[]
     */
    public function getReceivedRequests(): array {
        return $this->receivedRequests;
    }

    /**
     * Get the last received request
     *
     * @return \Cognesy\Http\Data\HttpRequest|null
     */
    public function getLastRequest(): ?HttpRequest {
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
    public function reset(): self {
        $this->receivedRequests = [];
        return $this;
    }

    /**
     * Clear all predefined responses
     *
     * @return self
     */
    public function clearResponses(): self {
        $this->responses = [];
        $this->expectations = [];
        return $this;
    }

    /**
     * Begin a fluent expectation definition.
     */
    public function on(): MockExpectation {
        return new MockExpectation($this);
    }

    /**
     * Alias for on().
     */
    public function expect(): MockExpectation {
        return $this->on();
    }

    /**
     * INTERNAL: Register a compiled expectation (used by MockExpectation::reply()).
     *
     * @param array{matchers: callable[], times: int|null, response: (callable|HttpResponse)} $compiled
     */
    public function registerExpectation(array $compiled): self {
        $this->expectations[] = $compiled;
        return $this;
    }
}
