<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

final class IndexingBatchStored extends RetrievalEvent
{
    public function __construct(
        public readonly string $sourceId,
        public readonly int $batchSize,
        public readonly int $acknowledged,
    ) {
        parent::__construct([
            'sourceId' => $sourceId,
            'batchSize' => $batchSize,
            'acknowledged' => $acknowledged,
        ]);
    }
}
