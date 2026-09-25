<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

use Psr\Log\LogLevel;

final class IndexingCompleted extends RetrievalEvent
{
    public string $logLevel = LogLevel::INFO;

    public function __construct(
        public readonly string $sourceId,
        public readonly int $indexed,
        public readonly int $removed,
        public readonly int $batches,
    ) {
        parent::__construct([
            'sourceId' => $sourceId,
            'indexed' => $indexed,
            'removed' => $removed,
            'batches' => $batches,
        ]);
    }
}
