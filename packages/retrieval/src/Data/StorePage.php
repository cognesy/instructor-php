<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class StorePage
{
    public function __construct(
        public SearchHits $hits,
        public ?StoreContinuation $continuation = null,
        public bool $windowExhausted = true,
    ) {}
}
