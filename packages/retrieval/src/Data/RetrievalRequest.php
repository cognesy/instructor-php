<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use Cognesy\Retrieval\Query\StoreQuery;
use Cognesy\Utils\Uuid;

final readonly class RetrievalRequest
{
    public string $id;

    public function __construct(
        public StoreQuery $query,
        ?string $id = null,
    ) {
        $this->id = $id ?? Uuid::correlationId();
    }

    public function withQuery(StoreQuery $query): self
    {
        return new self($query, $this->id);
    }
}
