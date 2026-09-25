<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\RetrievalRequest;
use Cognesy\Retrieval\Data\StoreCapabilities;
use Cognesy\Retrieval\Data\StorePage;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Data\WriteResult;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Events\RetrievalCompleted;
use Cognesy\Retrieval\Events\RetrievalFailed;
use Cognesy\Retrieval\Events\RetrievalStarted;
use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Retrieval\Query\VectorQuery;
use Cognesy\Retrieval\Retrieval;

it('defers one successful store query and memoizes its response', function () {
    $store = new CountingRetrievalStore(new InMemoryStore);
    $events = new EventDispatcher('retrieval-test');
    $seen = [];
    $events->wiretap(function (object $event) use (&$seen): void {
        $seen[] = $event;
    });
    $pending = Retrieval::fromStore($store, $events)
        ->withQuery(new VectorQuery(new Vector([1.0, 0.0])))
        ->pending();

    expect($store->queries)->toBe(0);
    $first = $pending->response();
    $second = $pending->response();

    $started = array_values(array_filter($seen, static fn (object $event): bool => $event instanceof RetrievalStarted));
    $completed = array_values(array_filter($seen, static fn (object $event): bool => $event instanceof RetrievalCompleted));
    expect($store->queries)->toBe(1)
        ->and($first)->toBe($second)
        ->and($started)->toHaveCount(1)
        ->and($completed)->toHaveCount(1)
        ->and($started[0]->executionId)->toBe($completed[0]->executionId)
        ->and($started[0]->data)->not->toHaveKey('vector')
        ->and($started[0]->data)->not->toHaveKey('content');
});

it('memoizes a terminal retrieval failure', function () {
    $store = new FailingRetrievalStore;
    $events = new EventDispatcher('retrieval-test');
    $seen = [];
    $events->wiretap(function (object $event) use (&$seen): void {
        $seen[] = $event;
    });
    $pending = Retrieval::fromStore($store, $events)
        ->create(new RetrievalRequest(new VectorQuery(new Vector([1.0]))));

    foreach ([1, 2] as $_) {
        try {
            $pending->get();
        } catch (RuntimeException) {
        }
    }

    $failed = array_values(array_filter($seen, static fn (object $event): bool => $event instanceof RetrievalFailed));
    expect($store->queries)->toBe(1)
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->errorType)->toBe(RuntimeException::class);
});

final class CountingRetrievalStore implements CanStoreDocuments
{
    public int $queries = 0;

    public function __construct(private readonly CanStoreDocuments $inner) {}

    public function upsert(VectorDocuments $documents): WriteResult
    {
        return $this->inner->upsert($documents);
    }

    public function remove(DocumentIds $ids): WriteResult
    {
        return $this->inner->remove($ids);
    }

    public function clear(): WriteResult
    {
        return $this->inner->clear();
    }

    public function query(StoreQuery $query): StorePage
    {
        $this->queries++;

        return $this->inner->query($query);
    }

    public function capabilities(): StoreCapabilities
    {
        return $this->inner->capabilities();
    }
}

final class FailingRetrievalStore implements CanStoreDocuments
{
    public int $queries = 0;

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
        $this->queries++;
        throw new RuntimeException('secret query content');
    }

    public function capabilities(): StoreCapabilities
    {
        return new StoreCapabilities([VectorQuery::class]);
    }
}
