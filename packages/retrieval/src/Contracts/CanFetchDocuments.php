<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\Data\DocumentIds;
use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\StoredDocuments;

interface CanFetchDocuments
{
    public function fetch(DocumentIds $ids, Projection $projection): StoredDocuments;
}
