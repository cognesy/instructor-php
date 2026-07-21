<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Extras\Middleware\RetryMiddleware;
use Cognesy\Http\Extras\Support\RetryPolicy;

it('uses retry-after from an exception response', function () {
    $request = new HttpRequest('https://example.test', 'GET', [], '', []);
    $response = HttpResponse::sync(503, ['Retry-After' => '1'], '');
    $attempts = 0;
    $handler = new class($attempts, $request, $response) implements CanHandleHttpRequest {
        public function __construct(
            private int &$attempts,
            private readonly HttpRequest $request,
            private readonly HttpResponse $response,
        ) {}

        public function handle(HttpRequest $request): HttpResponse {
            $this->attempts++;
            if ($this->attempts === 1) {
                throw new NetworkException('temporary failure', $this->request, $this->response);
            }

            return HttpResponse::sync(200, [], 'ok');
        }
    };

    $startedAt = microtime(true);
    $result = (new RetryMiddleware(new RetryPolicy(
        baseDelayMs: 0,
        maxDelayMs: 50,
        jitter: 'none',
    )))->handle($request, $handler);

    expect($result->statusCode())->toBe(200)
        ->and($attempts)->toBe(2)
        ->and(microtime(true) - $startedAt)->toBeGreaterThanOrEqual(0.04);
});
