<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\CircuitBreakerOpenException;
use Cognesy\Http\Middleware\CircuitBreakerMiddleware;
use Cognesy\Http\Middleware\CircuitBreakerPolicy;

final class AlwaysFailingHttpHandler implements CanHandleHttpRequest
{
    public int $calls = 0;

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        $this->calls++;
        return HttpResponse::sync(503, [], 'down');
    }
}

it('shares circuit failure state across middleware instances for the same host', function() {
    $request = new HttpRequest(
        url: 'https://api.example.com/health',
        method: 'GET',
        headers: [],
        body: '',
        options: [],
    );
    $policy = new CircuitBreakerPolicy(
        failureThreshold: 2,
        openForSec: 60,
    );
    $next = new AlwaysFailingHttpHandler();
    $first = new CircuitBreakerMiddleware($policy);
    $second = new CircuitBreakerMiddleware($policy);

    expect($first->handle($request, $next)->statusCode())->toBe(503);
    expect($second->handle($request, $next)->statusCode())->toBe(503);
    expect(fn() => $first->handle($request, $next))
        ->toThrow(CircuitBreakerOpenException::class);
    expect($next->calls)->toBe(2);
});
