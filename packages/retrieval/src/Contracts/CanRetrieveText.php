<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\PendingRetrieval;

interface CanRetrieveText
{
    public function retrieve(string $query, int $maxResults = 5): PendingRetrieval;
}
