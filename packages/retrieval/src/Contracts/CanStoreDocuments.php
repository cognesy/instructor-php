<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\StoreCapabilities;
use Cognesy\Retrieval\Data\StorePage;
use Cognesy\Retrieval\Data\VectorDocuments;
use Cognesy\Retrieval\Data\WriteResult;
use Cognesy\Retrieval\Query\StoreQuery;

interface CanStoreDocuments
{
    public function upsert(VectorDocuments $documents): WriteResult;

    public function remove(DocumentIds $ids): WriteResult;

    public function clear(): WriteResult;

    public function query(StoreQuery $query): StorePage;

    public function capabilities(): StoreCapabilities;
}
