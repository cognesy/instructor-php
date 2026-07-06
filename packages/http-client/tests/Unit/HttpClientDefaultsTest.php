<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Creation\HttpClientDefaults;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

// Ambient state is process-global; keep tests isolated.
beforeEach(fn () => HttpClientDefaults::clear());
afterEach(fn () => HttpClientDefaults::clear());

function noopMiddleware(): HttpMiddleware {
    return new class implements HttpMiddleware {
        public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
            return $next->handle($request);
        }
    };
}

test('defaults are empty out of the box (zero behavior change)', function () {
    expect(HttpClientDefaults::hasMiddleware())->toBeFalse()
        ->and(HttpClientDefaults::middleware())->toBe([]);
});

test('registered middleware accumulates in order', function () {
    $a = noopMiddleware();
    $b = noopMiddleware();
    HttpClientDefaults::withMiddleware($a);
    HttpClientDefaults::withMiddleware($b);

    expect(HttpClientDefaults::hasMiddleware())->toBeTrue()
        ->and(HttpClientDefaults::middleware())->toBe([$a, $b]);
});

test('applyTo is a no-op when nothing is registered', function () {
    $builder = new HttpClientBuilder();
    expect(HttpClientDefaults::applyTo($builder))->toBe($builder);
});

test('applyTo attaches ambient middleware so it runs on a built client', function () {
    // Behavioral proof: a spy middleware registered as ambient must intercept
    // requests of a client built via applyTo() — end-to-end through the stack.
    $seen = (object) ['count' => 0];
    $spy = new class($seen) implements HttpMiddleware {
        public function __construct(private object $seen) {}
        public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
            $this->seen->count++;
            return $next->handle($request);
        }
    };
    HttpClientDefaults::withMiddleware($spy);

    $client = HttpClientDefaults::applyTo(
        (new HttpClientBuilder())->withMock(function ($mock) {
            $mock->addResponse(
                HttpResponse::sync(200, [], '{"ok":true}'),
                url: 'https://api.example.local/ping',
                method: 'GET',
            );
        })
    )->create();

    $client->send(new HttpRequest('https://api.example.local/ping', 'GET', [], '', []))->get();

    expect($seen->count)->toBe(1);
});

test('clear removes all registered middleware', function () {
    HttpClientDefaults::withMiddleware(noopMiddleware());
    HttpClientDefaults::clear();
    expect(HttpClientDefaults::hasMiddleware())->toBeFalse();
});
