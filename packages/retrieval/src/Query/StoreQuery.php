<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Query;

use Cognesy\Retrieval\Data\Projection;
use Cognesy\Retrieval\Data\StoreContinuation;
use Cognesy\Retrieval\Filter\MetadataFilter;

interface StoreQuery
{
    public function limit(): int;

    public function filter(): MetadataFilter;

    public function projection(): Projection;

    public function continuation(): ?StoreContinuation;

    public function withContinuation(StoreContinuation $continuation): self;
}
