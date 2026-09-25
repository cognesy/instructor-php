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
use Cognesy\Retrieval\Core\RetrievalExecutionSession;
use Cognesy\Retrieval\Creation\StoreFactory;
use Cognesy\Retrieval\Cursor\RetrievalCursor;
use Cognesy\Retrieval\Data\RetrievalRequest;
use LogicException;
use Override;

final readonly class RetrievalRuntime implements CanCreateRetrieval
{
    public function __construct(
        private CanStoreDocuments $store,
        private CanHandleEvents $events,
        private string $driverName = '',
        private ?CanPrepareStoreQuery $queryPreparer = null,
    ) {}

    public static function fromConfig(
        RetrievalConfig $config,
        ?CanHandleEvents $events = null,
        ?CanProvideStoreDrivers $drivers = null,
    ): self {
        return self::fromProvider(new RetrievalProvider($config), $events, $drivers);
    }

    public static function fromProvider(
        RetrievalProvider $provider,
        ?CanHandleEvents $events = null,
        ?CanProvideStoreDrivers $drivers = null,
    ): self {
        return new self(
            store: StoreFactory::fromProvider($provider->store(), $drivers),
            events: $events ?? EventLog::root('retrieval.runtime'),
            driverName: $provider->store()->config()->driver,
        );
    }

    #[Override]
    public function create(RetrievalRequest $request): PendingRetrieval
    {
        return new PendingRetrieval(
            $request,
            new RetrievalExecutionSession(
                $request,
                $this->store,
                $this->events,
                $this->driverName,
                $this->queryPreparer,
            ),
        );
    }

    public function store(): CanStoreDocuments
    {
        return $this->store;
    }

    public function events(): CanHandleEvents
    {
        return $this->events;
    }

    public function cursor(RetrievalRequest $request): RetrievalCursor
    {
        if (! $this->store->capabilities()->rankedContinuation) {
            throw new LogicException('Selected store does not support ranked continuation');
        }

        return new RetrievalCursor(
            $this->create($request),
            fn ($continuation): PendingRetrieval => $this->create(
                $request->withQuery($request->query->withContinuation($continuation)),
            ),
        );
    }
}
