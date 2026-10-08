<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\BatchInference\Config\BatchReadRetryPolicy;
use Cognesy\Polyglot\BatchInference\Contracts\CanDelayBatchRead;
use Cognesy\Polyglot\BatchInference\Exceptions\BatchHttpException;
use Cognesy\Polyglot\BatchInference\Transport\BatchHttpTransport;

final class BatchRetryScriptedHttp implements CanSendHttpRequests, CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param list<HttpResponse|Throwable> $steps */
    public function __construct(private array $steps)
    {
    }

    public function send(HttpRequest $request): PendingHttpResponse
    {
        return new PendingHttpResponse($request, $this);
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $step = array_shift($this->steps) ?? throw new LogicException('No scripted batch retry step remains.');
        if ($step instanceof Throwable) {
            throw $step;
        }
        return $step;
    }
}

final class BatchRetryDelaySpy implements CanDelayBatchRead
{
    /** @var list<int> */
    public array $waits = [];

    public function wait(int $milliseconds): void
    {
        $this->waits[] = $milliseconds;
    }
}

it('retries bounded safe reads after server and network failures', function () {
    $http = new BatchRetryScriptedHttp([
        HttpResponse::sync(503, [], '{}'),
        new NetworkException('Temporary connection loss.'),
        HttpResponse::sync(200, [], '{"id":"batch_ok"}'),
    ]);
    $delay = new BatchRetryDelaySpy();
    $transport = new BatchHttpTransport($http, new BatchReadRetryPolicy(3, 10, 20), $delay);

    expect($transport->json('GET', 'https://example.test/job', []))->toBe(['id' => 'batch_ok'])
        ->and(count($http->requests))->toBe(3)
        ->and($delay->waits)->toBe([10, 20]);
});

it('does not retry mutation calls or non-retriable reads', function () {
    $mutation = new BatchRetryScriptedHttp([HttpResponse::sync(503, [], '{}')]);
    $mutationDelay = new BatchRetryDelaySpy();
    $post = new BatchHttpTransport($mutation, new BatchReadRetryPolicy(3, 10, 20), $mutationDelay);
    expect(fn () => $post->json('POST', 'https://example.test/jobs', [], ['name' => 'one']))->toThrow(BatchHttpException::class)
        ->and(count($mutation->requests))->toBe(1)
        ->and($mutationDelay->waits)->toBe([]);

    $auth = new BatchRetryScriptedHttp([HttpResponse::sync(401, [], '{}')]);
    $authDelay = new BatchRetryDelaySpy();
    $get = new BatchHttpTransport($auth, new BatchReadRetryPolicy(3, 10, 20), $authDelay);
    expect(fn () => $get->json('GET', 'https://example.test/job', []))->toThrow(BatchHttpException::class)
        ->and(count($auth->requests))->toBe(1)
        ->and($authDelay->waits)->toBe([]);
});

it('does not replay a result download after streaming has begun', function () {
    $chunks = (static function (): iterable {
        yield "first\n";
        throw new RuntimeException('Connection lost during result body.');
    })();
    $http = new BatchRetryScriptedHttp([HttpResponse::streamingFromIterable(200, [], $chunks)]);
    $delay = new BatchRetryDelaySpy();
    $transport = new BatchHttpTransport($http, new BatchReadRetryPolicy(3, 10, 20), $delay);
    $received = [];

    expect(function () use ($transport, &$received): void {
        foreach ($transport->stream('https://example.test/output', []) as $chunk) {
            $received[] = $chunk;
        }
    })->toThrow(RuntimeException::class)
        ->and($received)->toBe(["first\n"])
        ->and(count($http->requests))->toBe(1)
        ->and($delay->waits)->toBe([]);
});
