<?php

declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Data\SearchScore;
use Cognesy\Retrieval\Data\StoreCapabilities;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Data\StorePage;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Data\WriteResult;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Retrieval\Retrieval;

it('reuses the prepared query vector while traversing ranked pages', function () {
    $store = new PagedRetrievalStore;
    $query = new VectorQuery(new Vector([1.0, 0.0]));
    $cursor = Retrieval::fromStore($store)->withQuery($query)->cursor();

    $ids = [];
    foreach ($cursor as $hit) {
        $ids[] = $hit->id;
    }

    expect($ids)->toBe([1, 2, 3])
        ->and($store->vectorIdentities)->toHaveCount(2)
        ->and($store->vectorIdentities[0])->toBe($store->vectorIdentities[1]);
});

it('rejects ranked traversal when the store does not support it', function () {
    expect(fn () => Retrieval::fromStore(new InMemoryStore)
        ->withQuery(new VectorQuery(new Vector([1.0])))
        ->cursor())
        ->toThrow(LogicException::class, 'does not support ranked continuation');
});

final class PagedRetrievalStore implements CanStoreDocuments
{
    /** @var list<int> */
    public array $vectorIdentities = [];

    public function upsert(VectorDocuments $documents): WriteResult
    {
        return new WriteResult;
    }

    public function remove(DocumentIds $ids): WriteResult
    {
        return new WriteResult;
    }

    public function clear(): WriteResult
    {
        return new WriteResult;
    }

    public function query(StoreQuery $query): StorePage
    {
        if (! $query instanceof VectorQuery) {
            throw new LogicException('Expected vector query');
        }
        $this->vectorIdentities[] = spl_object_id($query->vector);
        $page = $query->continuation() === null ? 1 : 2;
        $ids = $page === 1 ? [1, 2] : [3];
        $hits = array_map(
            static fn (int $id): SearchHit => new SearchHit(
                $id,
                $id,
                new SearchScore(1.0, $query->metric),
            ),
            $ids,
        );

        return new StorePage(
            new SearchHits($hits),
            $page === 1 ? new StoreContinuation('paged-test', ['page' => 2]) : null,
        );
    }

    public function capabilities(): StoreCapabilities
    {
        return new StoreCapabilities([VectorQuery::class], rankedContinuation: true);
    }
}
