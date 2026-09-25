<?php

declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\ScanRequest;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\Qdrant\QdrantConfig;
use Cognesy\Retrieval\Drivers\Qdrant\QdrantStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Query\VectorQuery;

it('renders bounded Qdrant upsert and query requests without vectors by default', function () {
    $transport = new QdrantFakeTransport([
        ['status' => 'ok', 'result' => ['status' => 'completed']],
        ['status' => 'ok', 'result' => ['points' => [[
            'id' => 'doc-1',
            'score' => 0.95,
            'payload' => [
                'metadata' => ['tenant' => 'a'],
                'content' => 'evidence',
                'embedding_space' => 'space-v1',
            ],
        ]]]],
    ]);
    $store = new QdrantStore(
        new QdrantConfig('http://qdrant:6333', 'docs', 2, apiKey: 'secret'),
        HttpClient::fromDriver($transport),
    );
    $store->upsert(VectorDocuments::of(
        new VectorDocument('doc-1', new Vector([1.0, 0.0]), ['tenant' => 'a'], 'evidence', 'space-v1'),
    ));
    $page = $store->query(new VectorQuery(
        new Vector([1.0, 0.0]),
        filter: new MetadataEquals('tenant', 'a'),
        embeddingSpace: 'space-v1',
    ));

    $queryBody = json_decode($transport->requests[1]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    $upsertBody = json_decode($transport->requests[0]->body()->toString(), true, flags: JSON_THROW_ON_ERROR);
    expect($page->hits->first()?->id)->toBe('doc-1')
        ->and($page->hits->first()?->vector)->toBeNull()
        ->and($queryBody['with_vector'])->toBeFalse()
        ->and($queryBody['filter']['must'][0]['key'])->toBe('metadata.tenant')
        ->and($upsertBody['points'][0]['id'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($upsertBody['points'][0]['payload']['_retrieval'])->toBe([
            'id' => 'doc-1',
            'id_type' => 'string',
        ])
        ->and($transport->requests[0]->headers('api-key'))->toBe('secret');
});

it('maps Qdrant scroll offsets separately from ranked queries', function () {
    $transport = new QdrantFakeTransport([[
        'status' => 'ok',
        'result' => [
            'points' => [['id' => 1, 'payload' => ['metadata' => []]]],
            'next_page_offset' => 42,
        ],
    ]]);
    $store = new QdrantStore(
        new QdrantConfig('http://qdrant:6333', 'docs', 2),
        HttpClient::fromDriver($transport),
    );

    $page = $store->scan(new ScanRequest(1, projection: new Projection));

    expect($page->documents->count())->toBe(1)
        ->and($page->continuation?->driver)->toBe('qdrant')
        ->and($page->continuation?->position['offset'])->toBe(42)
        ->and($store->capabilities()->rankedContinuation)->toBeFalse();
});

it('stops streamed Qdrant responses at the configured byte bound', function () {
    $transport = new QdrantFakeTransport([['oversized' => str_repeat('x', 100)]]);
    $store = new QdrantStore(
        new QdrantConfig('http://qdrant:6333', 'docs', 2, maxResponseBytes: 20),
        HttpClient::fromDriver($transport),
    );

    expect(fn () => $store->scan(new ScanRequest))
        ->toThrow(RuntimeException::class, 'exceeds configured byte limit');
});

final class QdrantFakeTransport implements CanHandleHttpRequest
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses) {}

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses) ?? [];
        $body = json_encode($response, JSON_THROW_ON_ERROR);

        return HttpResponse::streamingFromIterable(200, [], str_split($body, 8));
    }
}
