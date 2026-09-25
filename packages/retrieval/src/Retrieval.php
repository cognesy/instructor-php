<?php

declare(strict_types=1);

namespace Cognesy\Retrieval;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Logging\EventLog;
use Cognesy\Retrieval\Config\RetrievalConfig;
use Cognesy\Retrieval\Contracts\CanCreateRetrieval;
use Cognesy\Retrieval\Contracts\CanPrepareStoreQuery;
use Cognesy\Retrieval\Contracts\CanProvideStoreDrivers;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Cursor\RetrievalCursor;
use Cognesy\Retrieval\Data\RetrievalRequest;
use Cognesy\Retrieval\Data\RetrievalResponse;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Query\SemanticQuery;
use Cognesy\Retrieval\Query\StoreQuery;
use InvalidArgumentException;
use LogicException;
use Override;

final class Retrieval implements CanCreateRetrieval
{
    private ?StoreQuery $query = null;

    public function __construct(
        private CanCreateRetrieval $runtime,
        private int $defaultMaxResults = 20,
    ) {
        if ($defaultMaxResults < 1) {
            throw new InvalidArgumentException('Retrieval result limit must be positive');
        }
    }

    public static function fromConfig(
        RetrievalConfig $config,
        ?CanHandleEvents $events = null,
        ?CanProvideStoreDrivers $drivers = null,
    ): self {
        return new self(RetrievalRuntime::fromConfig($config, $events, $drivers), $config->maxResults);
    }

    public static function fromProvider(
        RetrievalProvider $provider,
        ?CanHandleEvents $events = null,
        ?CanProvideStoreDrivers $drivers = null,
    ): self {
        return new self(
            RetrievalRuntime::fromProvider($provider, $events, $drivers),
            $provider->config()->maxResults,
        );
    }

    public static function fromRuntime(CanCreateRetrieval $runtime): self
    {
        return new self($runtime);
    }

    public static function fromStore(
        CanStoreDocuments $store,
        ?CanHandleEvents $events = null,
        string $driverName = 'custom',
        ?CanPrepareStoreQuery $queryPreparer = null,
        int $defaultMaxResults = 20,
    ): self {
        return new self(new RetrievalRuntime(
            $store,
            $events ?? EventLog::root('retrieval.runtime'),
            $driverName,
            $queryPreparer,
        ), $defaultMaxResults);
    }

    public function withQuery(StoreQuery|string $query): self
    {
        $copy = clone $this;
        $copy->query = is_string($query)
            ? new SemanticQuery($query, maxResults: $this->defaultMaxResults)
            : $query;

        return $copy;
    }

    public function get(): SearchHits
    {
        return $this->pending()->get();
    }

    public function response(): RetrievalResponse
    {
        return $this->pending()->response();
    }

    public function pending(): PendingRetrieval
    {
        if ($this->query === null) {
            throw new InvalidArgumentException('Retrieval query is required');
        }

        return $this->create(new RetrievalRequest($this->query));
    }

    public function cursor(): RetrievalCursor
    {
        if ($this->query === null) {
            throw new InvalidArgumentException('Retrieval query is required');
        }
        if (! $this->runtime instanceof RetrievalRuntime) {
            throw new LogicException('Custom retrieval runtime does not expose cursor traversal');
        }

        return $this->runtime->cursor(new RetrievalRequest($this->query));
    }

    #[Override]
    public function create(RetrievalRequest $request): PendingRetrieval
    {
        return $this->runtime->create($request);
    }
}
