<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing\Contracts;

use Cognesy\Retrieval\Data\DocumentIds;

interface CanTrackSourceRecords
{
    public function recordsFor(string $sourceId): DocumentIds;

    public function replace(string $sourceId, DocumentIds $records): void;
}
