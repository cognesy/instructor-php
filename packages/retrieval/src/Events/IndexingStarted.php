<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Events;

final class IndexingStarted extends RetrievalEvent
{
    public function __construct(public readonly string $sourceId)
    {
        parent::__construct(['sourceId' => $sourceId]);
    }
}
