<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Contracts;

use Cognesy\Retrieval\Data\RetrievalRequest;
use Cognesy\Retrieval\PendingRetrieval;

interface CanCreateRetrieval
{
    public function create(RetrievalRequest $request): PendingRetrieval;
}
