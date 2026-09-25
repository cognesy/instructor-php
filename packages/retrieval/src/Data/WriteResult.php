<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class WriteResult
{
    public function __construct(
        public int $acknowledged = 0,
        public int $missing = 0,
        public int $unknown = 0,
    ) {}
}
