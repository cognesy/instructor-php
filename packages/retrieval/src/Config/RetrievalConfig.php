<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Config;

use InvalidArgumentException;

final readonly class RetrievalConfig
{
    public function __construct(
        public StoreConfig $store = new StoreConfig,
        public int $maxResults = 20,
    ) {
        if ($maxResults < 1) {
            throw new InvalidArgumentException('Retrieval result limit must be positive');
        }
    }

    public function withStore(StoreConfig $store): self
    {
        return new self($store, $this->maxResults);
    }

    public function withMaxResults(int $maxResults): self
    {
        return new self($this->store, $maxResults);
    }
}
