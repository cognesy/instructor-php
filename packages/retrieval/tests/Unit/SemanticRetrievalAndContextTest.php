<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Context\ContextBudget;
use Cognesy\Retrieval\Data\DistanceMetric;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Data\SearchScore;
use Cognesy\Retrieval\Data\VectorDocument;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Query\SemanticQuery;
use Cognesy\Retrieval\Retrieval;
use Cognesy\Retrieval\SemanticRetriever;
use Cognesy\Retrieval\Vectorization\SemanticQueryVectorizer;
use Cognesy\Retrieval\Vectorization\Vectorizer;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Psr\EventDispatcher\EventDispatcherInterface;

it('defers semantic query vectorization until pending retrieval is consumed', function () {
    $events = new EventDispatcher('semantic-retrieval-test');
    $embeddings = new CountingSemanticEmbeddings($events);
    $store = new InMemoryStore;
    $store->upsert(VectorDocuments::of(
        new VectorDocument('doc', new Vector([5.0, 1.0]), content: 'alpha evidence', embeddingSpace: 'space-v1'),
    ));
    $retrieval = Retrieval::fromStore(
        store: $store,
        events: $events,
        queryPreparer: new SemanticQueryVectorizer(new Vectorizer($embeddings, 'space-v1')),
    );
    $pending = (new SemanticRetriever($retrieval, embeddingSpace: 'space-v1'))->retrieve('alpha', 3);

    expect($embeddings->requests)->toBe(0);
    $first = $pending->get();
    $second = $pending->get();

    expect($embeddings->requests)->toBe(1)
        ->and($first)->toBe($second)
        ->and($first->first()?->id)->toBe('doc');
});

it('supports semantic strings directly on the Retrieval facade', function () {
    $events = new EventDispatcher('semantic-retrieval-test');
    $embeddings = new CountingSemanticEmbeddings($events);
    $store = new InMemoryStore;
    $store->upsert(VectorDocuments::of(
        new VectorDocument('doc', new Vector([5.0, 1.0]), content: 'alpha evidence'),
    ));
    $retrieval = Retrieval::fromStore(
        store: $store,
        events: $events,
        queryPreparer: new SemanticQueryVectorizer(new Vectorizer($embeddings, 'space-v1')),
        defaultMaxResults: 1,
    );

    $pending = $retrieval->withQuery('alpha')->pending();

    expect($embeddings->requests)->toBe(0)
        ->and($pending->get()->count())->toBe(1)
        ->and($embeddings->requests)->toBe(1);
});

it('fails semantic queries explicitly when no query vectorizer is configured', function () {
    $pending = Retrieval::fromStore(new InMemoryStore, new EventDispatcher('semantic-retrieval-test'))
        ->withQuery(new SemanticQuery('alpha'))
        ->pending();

    expect(fn () => $pending->get())->toThrow(InvalidArgumentException::class, 'supports only');
});

it('assembles stable citations within exact byte token evidence and excerpt budgets', function () {
    $hits = new SearchHits([
        semanticHit('a', 1, 'alpha evidence is deliberately long', ['source' => 'one', 'secret' => 'hidden']),
        semanticHit('b', 2, 'beta evidence'),
        semanticHit('a', 3, 'duplicate evidence'),
        semanticHit('empty', 4, null),
    ]);
    $budget = new ContextBudget(maxBytes: 40, maxTokens: 10, maxEvidence: 2, maxExcerptBytes: 20);
    $context = (new ContextAssembler(new FourByteTokenCounter, ['source']))->assemble($hits, $budget);

    expect($context->bytes)->toBeLessThanOrEqual(40)
        ->and($context->tokens)->toBeLessThanOrEqual(10)
        ->and($context->count())->toBe(2)
        ->and(array_keys($context->citations()))->toBe(['S1', 'S2'])
        ->and($context->evidence[0]->metadata)->toBe(['source' => 'one'])
        ->and($context->text)->toStartWith('[S1] ')
        ->and($context->text)->toContain('[S2] ')
        ->and($context->text)->not->toContain('secret');
});

it('returns an explicit empty context when no hit has usable content', function () {
    $hits = new SearchHits([semanticHit('a', 1, null)]);
    $context = (new ContextAssembler(new FourByteTokenCounter))->assemble($hits);

    expect($context->text)->toBe('')
        ->and($context->count())->toBe(0)
        ->and($context->omitted)->toBe(1);
});

/** @param array<string, mixed> $metadata */
function semanticHit(string $id, int $rank, ?string $content, array $metadata = []): SearchHit
{
    return new SearchHit(
        $id,
        $rank,
        new SearchScore(1.0 / $rank, DistanceMetric::Cosine),
        $metadata,
        $content,
    );
}

final class CountingSemanticEmbeddings implements CanCreateEmbeddings
{
    public int $requests = 0;

    public function __construct(private readonly EventDispatcherInterface $events) {}

    public function create(EmbeddingsRequest $request): PendingEmbeddings
    {
        $this->requests++;

        return new PendingEmbeddings($request, new SemanticVectorizationDriver, $this->events);
    }
}

final readonly class SemanticVectorizationDriver implements CanHandleVectorization
{
    public function handle(EmbeddingsRequest $request): EmbeddingsResponse
    {
        return new EmbeddingsResponse([
            new Vector([strlen($request->inputs()[0] ?? ''), 1.0], 0),
        ]);
    }
}

final readonly class FourByteTokenCounter implements CanCountTokens
{
    public function tokenCount(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
