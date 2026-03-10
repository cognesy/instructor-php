<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

it('retries embeddings requests on configured HTTP status responses', function () {
    $state = (object) [
        'sendCalls' => 0,
        'responses' => [
            HttpResponse::sync(429, ['content-type' => 'application/json'], '{"error":"rate limited"}'),
            HttpResponse::sync(200, ['content-type' => 'application/json'], '{"data":[{"embedding":[0.1,0.2]}]}'),
        ],
    ];

    $driver = new BaseEmbedDriver(
        config: new EmbeddingsConfig(),
        httpClient: queuedHttpClient($state),
        events: new EventDispatcher(),
        requestAdapter: fixedEmbeddingsRequestAdapter(),
        responseAdapter: fixedEmbeddingsResponseAdapter(),
    );

    $response = (new PendingEmbeddings(
        request: new EmbeddingsRequest(
            input: ['hello'],
            retryPolicy: new EmbeddingsRetryPolicy(
                maxAttempts: 2,
                baseDelayMs: 0,
                retryOnStatus: [429],
            ),
        ),
        driver: $driver,
        events: new EventDispatcher(),
    ))->get();

    expect($state->sendCalls)->toBe(2);
    expect($response->first()?->values())->toBe([0.1, 0.2]);
});

it('does not retry embeddings requests on non-retriable HTTP status responses', function () {
    $state = (object) [
        'sendCalls' => 0,
        'responses' => [
            HttpResponse::sync(400, ['content-type' => 'application/json'], '{"error":"bad request"}'),
        ],
    ];

    $driver = new BaseEmbedDriver(
        config: new EmbeddingsConfig(),
        httpClient: queuedHttpClient($state),
        events: new EventDispatcher(),
        requestAdapter: fixedEmbeddingsRequestAdapter(),
        responseAdapter: fixedEmbeddingsResponseAdapter(),
    );

    $request = new EmbeddingsRequest(
        input: ['hello'],
        retryPolicy: new EmbeddingsRetryPolicy(
            maxAttempts: 3,
            baseDelayMs: 0,
            retryOnStatus: [429],
        ),
    );

    expect(fn() => (new PendingEmbeddings(
        request: $request,
        driver: $driver,
        events: new EventDispatcher(),
    ))->get())->toThrow(HttpRequestException::class);
    expect($state->sendCalls)->toBe(1);
});

function queuedHttpClient(object $state): CanSendHttpRequests {
    return new class($state) implements CanSendHttpRequests {
        public function __construct(
            private object $state,
        ) {}

        public function send(HttpRequest $request): PendingHttpResponse {
            $this->state->sendCalls++;

            return new PendingHttpResponse(
                request: $request,
                driver: new class($this->state) implements CanHandleHttpRequest {
                    public function __construct(
                        private object $state,
                    ) {}

                    public function handle(HttpRequest $request): HttpResponse {
                        return array_shift($this->state->responses);
                    }
                },
            );
        }
    };
}

function fixedEmbeddingsRequestAdapter(): EmbedRequestAdapter {
    return new class implements EmbedRequestAdapter {
        public function toHttpClientRequest(EmbeddingsRequest $request): HttpRequest {
            return new HttpRequest(
                url: 'https://api.example.com/v1/embeddings',
                method: 'POST',
                headers: ['content-type' => 'application/json'],
                body: $request->toArray(),
                options: [],
            );
        }
    };
}

function fixedEmbeddingsResponseAdapter(): EmbedResponseAdapter {
    return new class implements EmbedResponseAdapter {
        public function fromResponse(array $data): EmbeddingsResponse {
            return new EmbeddingsResponse([
                new Vector(values: [0.1, 0.2], id: 0),
            ]);
        }
    };
}
