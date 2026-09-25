<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing;

use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Indexing\Contracts\CanTrackSourceRecords;
use Override;

final class InMemorySourceRecordManifest implements CanTrackSourceRecords
{
    /** @var array<string, DocumentIds> */
    private array $records = [];

    #[Override]
    public function recordsFor(string $sourceId): DocumentIds
    {
        return $this->records[$sourceId] ?? new DocumentIds;
    }

    #[Override]
    public function replace(string $sourceId, DocumentIds $records): void
    {
        $this->records[$sourceId] = $records;
    }
}
