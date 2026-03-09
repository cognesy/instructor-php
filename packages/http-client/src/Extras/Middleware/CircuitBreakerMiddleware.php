<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\ApcuCircuitBreakerStateStore;
use Cognesy\Http\Extras\Support\CanStoreCircuitBreakerState;
use Cognesy\Http\Extras\Support\CircuitBreakerPolicy;
use Cognesy\Http\Extras\Support\CircuitBreakerState;
use Cognesy\Http\Extras\Support\InMemoryCircuitBreakerStateStore;
use Cognesy\Http\Exceptions\CircuitBreakerOpenException;

final class CircuitBreakerMiddleware implements HttpMiddleware
{
    private static ?InMemoryCircuitBreakerStateStore $fallbackStore = null;

    private readonly CanStoreCircuitBreakerState $store;

    public function __construct(
        private readonly CircuitBreakerPolicy $policy = new CircuitBreakerPolicy(),
        ?CanStoreCircuitBreakerState $store = null,
    ) {
        $this->store = $store ?? self::defaultStateStore();
    }

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $key = $this->keyFor($request);
        $circuit = $this->store->load($key) ?? $this->newCircuit();

        if ($circuit->isOpen()) {
            if ($this->isOpenExpired($circuit)) {
                $circuit = $circuit->asHalfOpen();
            } else {
                $this->store->save($key, $circuit);
                throw new CircuitBreakerOpenException("Circuit open for {$key}", $request);
            }
        }

        if ($circuit->isHalfOpen() && $circuit->halfOpenRequests >= $this->policy->halfOpenMaxRequests) {
            $this->store->save($key, $circuit);
            throw new CircuitBreakerOpenException("Circuit half-open limit reached for {$key}", $request);
        }

        if ($circuit->isHalfOpen()) {
            $circuit = $circuit->withHalfOpenRequests($circuit->halfOpenRequests + 1);
        }

        try {
            $response = $next->handle($request);
        } catch (\Throwable $error) {
            if ($this->policy->isFailureException($error)) {
                $circuit = $this->recordFailure($circuit);
            }
            $this->store->save($key, $circuit);
            throw $error;
        }

        if ($this->policy->isFailureResponse($response)) {
            $circuit = $this->recordFailure($circuit);
            $this->store->save($key, $circuit);
            return $response;
        }

        $circuit = $this->recordSuccess($circuit);
        $this->store->save($key, $circuit);

        return $response;
    }

    private function keyFor(HttpRequest $request): string {
        $host = parse_url($request->url(), PHP_URL_HOST) ?: 'unknown-host';
        return $host;
    }

    private function newCircuit(): CircuitBreakerState {
        return CircuitBreakerState::fresh();
    }

    private function isOpenExpired(CircuitBreakerState $circuit): bool {
        return (time() - $circuit->lastFailure) >= $this->policy->openForSec;
    }

    private function recordFailure(CircuitBreakerState $circuit): CircuitBreakerState {
        $updatedCircuit = $circuit
            ->withFailures($circuit->failures + 1)
            ->withLastFailure(time());

        if ($updatedCircuit->isHalfOpen() || $updatedCircuit->failures >= $this->policy->failureThreshold) {
            return $updatedCircuit->withState(CircuitBreakerState::STATE_OPEN);
        }

        return $updatedCircuit;
    }

    private function recordSuccess(CircuitBreakerState $circuit): CircuitBreakerState {
        if ($circuit->isHalfOpen()) {
            $updatedCircuit = $circuit->withHalfOpenSuccesses($circuit->halfOpenSuccesses + 1);
            if ($updatedCircuit->halfOpenSuccesses >= $this->policy->successThreshold) {
                return $updatedCircuit->asClosed();
            }
            return $updatedCircuit;
        }

        return $circuit->withFailures(0);
    }

    private static function defaultStateStore(): CanStoreCircuitBreakerState {
        if (ApcuCircuitBreakerStateStore::isSupported()) {
            return new ApcuCircuitBreakerStateStore();
        }

        self::$fallbackStore ??= new InMemoryCircuitBreakerStateStore();
        return self::$fallbackStore;
    }
}
