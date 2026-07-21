<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Extras\Middleware\IdempotencyMiddleware;
use Cognesy\Http\Extras\Middleware\RetryMiddleware;
use Cognesy\Http\Extras\Support\RetryPolicy;
use Cognesy\Http\Middleware\MiddlewareHandler;

it('does not retry POST responses by default', function () {
    $handler = new class implements CanHandleHttpRequest {
        public int $attempts = 0;

        public function handle(HttpRequest $request): HttpResponse {
            $this->attempts++;
            return HttpResponse::sync(503, [], 'temporary');
        }
    };
    $request = new HttpRequest('https://example.test', 'POST', [], 'payload', []);

    $response = (new RetryMiddleware(new RetryPolicy(
        maxRetries: 1,
        baseDelayMs: 0,
        maxDelayMs: 0,
        jitter: 'none',
    )))->handle($request, $handler);

    expect($response->statusCode())->toBe(503)
        ->and($handler->attempts)->toBe(1);
});

it('does not retry POST transport exceptions by default', function () {
    $handler = new class implements CanHandleHttpRequest {
        public int $attempts = 0;

        public function handle(HttpRequest $request): HttpResponse {
            $this->attempts++;
            throw new NetworkException('temporary failure', $request);
        }
    };
    $request = new HttpRequest('https://example.test', 'PATCH', [], 'payload', []);

    expect(fn() => (new RetryMiddleware(new RetryPolicy(
        maxRetries: 1,
        baseDelayMs: 0,
        maxDelayMs: 0,
        jitter: 'none',
    )))->handle($request, $handler))
        ->toThrow(NetworkException::class);

    expect($handler->attempts)->toBe(1);
});

it('reuses generated idempotency keys in both middleware orderings', function () {
    foreach ([
        'retry-then-idempotency' => [
            new RetryMiddleware(new RetryPolicy(
                maxRetries: 1,
                baseDelayMs: 0,
                maxDelayMs: 0,
                jitter: 'none',
                retryNonIdempotentMethods: true,
            )),
            new IdempotencyMiddleware(
                keyProvider: static fn(HttpRequest $request): string => 'key-' . $request->id,
            ),
        ],
        'idempotency-then-retry' => [
            new IdempotencyMiddleware(
                keyProvider: static fn(HttpRequest $request): string => 'key-' . $request->id,
            ),
            new RetryMiddleware(new RetryPolicy(
                maxRetries: 1,
                baseDelayMs: 0,
                maxDelayMs: 0,
                jitter: 'none',
                retryNonIdempotentMethods: true,
            )),
        ],
    ] as $middleware) {
        $handler = new class implements CanHandleHttpRequest {
            public int $attempts = 0;
            /** @var list<string> */
            public array $keys = [];

            public function handle(HttpRequest $request): HttpResponse {
                $this->attempts++;
                foreach ($request->headers() as $name => $value) {
                    if (strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                        $this->keys[] = (string) $value;
                    }
                }

                return HttpResponse::sync($this->attempts === 1 ? 503 : 200, [], '');
            }
        };
        $request = new HttpRequest('https://example.test', 'POST', [], 'payload', []);

        $response = (new MiddlewareHandler(
            driver: $handler,
            middleware: $middleware,
        ))->handle($request);

        expect($response->statusCode())->toBe(200)
            ->and($handler->attempts)->toBe(2)
            ->and($handler->keys)->toHaveCount(2)
            ->and($handler->keys[0])->not->toBeEmpty()
            ->and($handler->keys[1])->toBe($handler->keys[0]);
    }
});

it('recognizes an existing idempotency key regardless of header casing', function () {
    $handler = new class implements CanHandleHttpRequest {
        public ?HttpRequest $request = null;

        public function handle(HttpRequest $request): HttpResponse {
            $this->request = $request;
            return HttpResponse::sync(200, [], '');
        }
    };
    $request = new HttpRequest(
        'https://example.test',
        'POST',
        ['iDeMpOtEnCy-KeY' => 'provided'],
        '',
        [],
    );

    (new IdempotencyMiddleware())->handle($request, $handler);

    expect($handler->request?->headers())->toBe(['iDeMpOtEnCy-KeY' => 'provided']);
});
