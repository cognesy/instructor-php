<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
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

        if ($circuit['state'] === 'open') {
            if ($this->isOpenExpired($circuit)) {
                $circuit['state'] = 'half_open';
                $circuit['halfOpenRequests'] = 0;
                $circuit['halfOpenSuccesses'] = 0;
            } else {
                $this->store->save($key, $circuit);
                throw new CircuitBreakerOpenException("Circuit open for {$key}", $request);
            }
        }

        if ($circuit['state'] === 'half_open' && $circuit['halfOpenRequests'] >= $this->policy->halfOpenMaxRequests) {
            $this->store->save($key, $circuit);
            throw new CircuitBreakerOpenException("Circuit half-open limit reached for {$key}", $request);
        }

        if ($circuit['state'] === 'half_open') {
            $circuit['halfOpenRequests']++;
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

    private function newCircuit(): array {
        return [
            'state' => 'closed',
            'failures' => 0,
            'lastFailure' => 0,
            'halfOpenRequests' => 0,
            'halfOpenSuccesses' => 0,
        ];
    }

    private function isOpenExpired(array $circuit): bool {
        return (time() - $circuit['lastFailure']) >= $this->policy->openForSec;
    }

    private function recordFailure(array $circuit): array {
        $circuit['failures']++;
        $circuit['lastFailure'] = time();

        if ($circuit['state'] === 'half_open' || $circuit['failures'] >= $this->policy->failureThreshold) {
            $circuit['state'] = 'open';
        }

        return $circuit;
    }

    private function recordSuccess(array $circuit): array {
        if ($circuit['state'] === 'half_open') {
            $circuit['halfOpenSuccesses']++;
            if ($circuit['halfOpenSuccesses'] >= $this->policy->successThreshold) {
                $circuit['state'] = 'closed';
                $circuit['failures'] = 0;
                $circuit['halfOpenRequests'] = 0;
                $circuit['halfOpenSuccesses'] = 0;
            }
            return $circuit;
        }

        $circuit['failures'] = 0;
        return $circuit;
    }

    private static function defaultStateStore(): CanStoreCircuitBreakerState {
        if (ApcuCircuitBreakerStateStore::isSupported()) {
            return new ApcuCircuitBreakerStateStore();
        }

        self::$fallbackStore ??= new InMemoryCircuitBreakerStateStore();
        return self::$fallbackStore;
    }
}
