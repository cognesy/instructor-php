<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Events\IndexingBatchStored;
use Cognesy\Retrieval\Indexing\Data\TextDocument;
use Cognesy\Retrieval\Indexing\DocumentIndexer;
use Cognesy\Retrieval\Indexing\DocumentProcessor;
use Cognesy\Retrieval\Indexing\InMemorySourceRecordManifest;
use Cognesy\Retrieval\Indexing\Transformation\TextSplitter;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Retrieval\Vectorization\Vectorizer;
use Psr\EventDispatcher\EventDispatcherInterface;

it('indexes transformed documents in bounded batches with stable ids', function () {
    $events = new EventDispatcher('indexing-test');
    $seen = [];
    $events->wiretap(function (object $event) use (&$seen): void {
        $seen[] = $event;
    });
    $store = new InMemoryStore;
    $manifest = new InMemorySourceRecordManifest;
    $processor = new DocumentProcessor(
        store: $store,
        vectorizer: new Vectorizer(new FakeEmbeddingsRuntime($events), 'space-v1'),
        events: $events,
        manifest: $manifest,
        transformers: [new TextSplitter(3)],
        batchSize: 2,
    );
    $source = new TextDocument('doc', 'abcdefg', sourceId: 'source', sourceVersion: 'v1');

    $first = (new DocumentIndexer($processor))->index($source);
    $firstIds = $manifest->recordsFor('source')->all();
    $second = (new DocumentIndexer($processor))->index($source);
    $secondIds = $manifest->recordsFor('source')->all();
    $batches = array_values(array_filter($seen, static fn (object $event): bool => $event instanceof IndexingBatchStored));

    expect($first->indexed)->toBe(3)
        ->and($first->batches)->toBe(2)
        ->and($second->indexed)->toBe(3)
        ->and($firstIds)->toBe($secondIds)
        ->and($batches[0]->batchSize)->toBe(2);
});

it('removes stale chunks when a source is replaced', function () {
    $events = new EventDispatcher('indexing-test');
    $store = new InMemoryStore;
    $manifest = new InMemorySourceRecordManifest;
    $processor = new DocumentProcessor(
        $store,
        new Vectorizer(new FakeEmbeddingsRuntime($events), 'space-v1'),
        $events,
        $manifest,
        transformers: [new TextSplitter(3)],
    );
    $indexer = new DocumentIndexer($processor);
    $indexer->index(new TextDocument('doc', 'abcdefg', sourceId: 'source', sourceVersion: 'v1'));

    $report = $indexer->index(new TextDocument('doc', 'abc', sourceId: 'source', sourceVersion: 'v2'));
    $page = $store->query(new VectorQuery(new Vector([3.0, 1.0]), maxResults: 10, embeddingSpace: 'space-v1'));

    expect($report->removed)->toBe(3)
        ->and($page->hits->count())->toBe(1);
});

it(
    'rejects missing duplicate and out of range embedding indexes',
    /** @param list<int> $ids */
    function (array $ids) {
        $events = new EventDispatcher('indexing-test');
        $vectorizer = new Vectorizer(new FakeEmbeddingsRuntime($events, $ids), 'space-v1');

        expect(fn () => $vectorizer->vectorize([
            new TextDocument('a', 'a'),
            new TextDocument('b', 'b'),
        ]))->toThrow(InvalidArgumentException::class);
    },
)->with([
    'missing cardinality' => [[0]],
    'duplicate index' => [[0, 0]],
    'out of range index' => [[0, 2]],
]);

final readonly class FakeEmbeddingsRuntime implements CanCreateEmbeddings
{
    /** @param array<int, int>|null $ids */
    public function __construct(
        private EventDispatcherInterface $events,
        private ?array $ids = null,
    ) {}

    public function create(EmbeddingsRequest $request): PendingEmbeddings
    {
        return new PendingEmbeddings(
            $request,
            new FakeVectorizationDriver($this->ids),
            $this->events,
        );
    }
}

final readonly class FakeVectorizationDriver implements CanHandleVectorization
{
    /** @param array<int, int>|null $ids */
    public function __construct(private ?array $ids = null) {}

    public function handle(EmbeddingsRequest $request): EmbeddingsResponse
    {
        $ids = $this->ids ?? array_keys($request->inputs());
        $vectors = [];
        foreach ($ids as $id) {
            $vectors[] = new Vector([strlen($request->inputs()[$id] ?? ''), 1.0], $id);
        }

        return new EmbeddingsResponse($vectors);
    }
}
