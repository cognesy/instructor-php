<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;

/**
 * Regression: PendingEmbeddings::get() must memoize the response.
 *
 * Before the fix, makeResponse() was public and bypassed memoization.
 * Now it is private, and get() caches the result so the driver is
 * called exactly once regardless of how many times get() is invoked.
 */
it('memoizes the response across multiple get() calls', function () {
    $callCount = 0;

    $driver = new class($callCount) implements CanHandleVectorization {
        public function __construct(private int &$calls) {}

        public function handle(EmbeddingsRequest $request): HttpResponse {
            $this->calls++;
            return new HttpResponse(
                statusCode: 200,
                body: json_encode([
                    'data' => [['index' => 0, 'embedding' => [0.1, 0.2]]],
                    'usage' => ['prompt_tokens' => 2],
                ]),
                headers: ['content-type' => 'application/json'],
                isStreamed: false,
            );
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            $vectors = array_map(
                fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                $data['data'] ?? [],
            );
            return new EmbeddingsResponse($vectors);
        }
    };

    $request = new EmbeddingsRequest(input: ['hello']);
    $pending = new PendingEmbeddings($request, $driver, new EventDispatcher());

    $first = $pending->get();
    $second = $pending->get();
    $third = $pending->get();

    // Same instance returned each time
    expect($second)->toBe($first);
    expect($third)->toBe($first);
    // Driver called exactly once
    expect($callCount)->toBe(1);
});

it('returns vectors from memoized response', function () {
    $driver = new class implements CanHandleVectorization {
        public function handle(EmbeddingsRequest $request): HttpResponse {
            return new HttpResponse(
                statusCode: 200,
                body: json_encode([
                    'data' => [
                        ['index' => 0, 'embedding' => [0.5, 0.6, 0.7]],
                        ['index' => 1, 'embedding' => [0.8, 0.9, 1.0]],
                    ],
                    'usage' => ['prompt_tokens' => 4],
                ]),
                headers: ['content-type' => 'application/json'],
                isStreamed: false,
            );
        }

        public function fromData(array $data): ?EmbeddingsResponse {
            $vectors = array_map(
                fn($item) => new Vector(values: $item['embedding'], id: $item['index']),
                $data['data'] ?? [],
            );
            return new EmbeddingsResponse($vectors);
        }
    };

    $request = new EmbeddingsRequest(input: ['hello', 'world']);
    $pending = new PendingEmbeddings($request, $driver, new EventDispatcher());

    $response = $pending->get();
    expect($response->vectors())->toHaveCount(2);
    expect($response->first()->values())->toBe([0.5, 0.6, 0.7]);
});
