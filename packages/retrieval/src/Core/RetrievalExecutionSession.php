<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Retrieval\Contracts\CanPrepareStoreQuery;
use Cognesy\Retrieval\Contracts\CanStoreDocuments;
use Cognesy\Retrieval\Data\RetrievalRequest;
use Cognesy\Retrieval\Data\RetrievalResponse;
use Cognesy\Retrieval\Events\RetrievalCompleted;
use Cognesy\Retrieval\Events\RetrievalFailed;
use Cognesy\Retrieval\Events\RetrievalStarted;
use Cognesy\Retrieval\Events\StoreQueryCompleted;
use Cognesy\Retrieval\Events\StoreQueryStarted;
use Cognesy\Retrieval\Telemetry\RetrievalTelemetry;
use Cognesy\Utils\Uuid;
use LogicException;
use Throwable;

final class RetrievalExecutionSession
{
    private readonly string $executionId;

    private ?RetrievalResponse $response = null;

    private ?Throwable $terminalError = null;

    private bool $running = false;

    public function __construct(
        private readonly RetrievalRequest $request,
        private readonly CanStoreDocuments $store,
        private readonly CanHandleEvents $events,
        private readonly string $driverName,
        private readonly ?CanPrepareStoreQuery $queryPreparer = null,
    ) {
        $this->executionId = Uuid::correlationId();
    }

    public function response(): RetrievalResponse
    {
        if ($this->terminalError !== null) {
            throw $this->terminalError;
        }
        if ($this->response !== null) {
            return $this->response;
        }
        if ($this->running) {
            throw new LogicException('Retrieval execution is already running');
        }

        return $this->execute();
    }

    public function executionId(): string
    {
        return $this->executionId;
    }

    private function execute(): RetrievalResponse
    {
        $this->running = true;
        $startedAt = hrtime(true);
        $this->events->dispatch(new RetrievalStarted(
            executionId: $this->executionId,
            requestId: $this->request->id,
            driver: $this->driverName,
            queryType: $this->request->query::class,
            limit: $this->request->query->limit(),
            data: RetrievalTelemetry::execution($this->request, $this->executionId),
        ));
        try {
            $query = $this->queryPreparer?->prepare($this->request->query) ?? $this->request->query;
            $this->events->dispatch(new StoreQueryStarted(
                $this->executionId,
                $this->request->id,
                $this->driverName,
            ));
            $storeStartedAt = hrtime(true);
            $page = $this->store->query($query);
            $this->events->dispatch(new StoreQueryCompleted(
                $this->executionId,
                $this->request->id,
                $this->driverName,
                $page->hits->count(),
                self::durationMs($storeStartedAt),
            ));
            $this->response = new RetrievalResponse(
                $this->request,
                $page,
                $this->executionId,
                self::durationMs($startedAt),
            );
            $this->events->dispatch(new RetrievalCompleted(
                $this->executionId,
                $this->request->id,
                $this->driverName,
                $page->hits->count(),
                $this->response->durationMs,
            ));

            return $this->response;
        } catch (Throwable $error) {
            $this->terminalError = $error;
            $this->events->dispatch(new RetrievalFailed(
                $this->executionId,
                $this->request->id,
                $this->driverName,
                $error::class,
                self::durationMs($startedAt),
            ));
            throw $error;
        } finally {
            $this->running = false;
        }
    }

    private static function durationMs(int $startedAt): float
    {
        return (hrtime(true) - $startedAt) / 1_000_000;
    }
}
