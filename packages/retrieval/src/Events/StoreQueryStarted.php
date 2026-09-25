<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

final class StoreQueryStarted extends RetrievalEvent
{
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $driver,
    ) {
        parent::__construct([
            'executionId' => $executionId,
            'requestId' => $requestId,
            'driver' => $driver,
        ]);
    }
}
