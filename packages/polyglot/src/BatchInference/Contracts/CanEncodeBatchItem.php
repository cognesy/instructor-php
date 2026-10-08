<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Contracts;

use Cognesy\Polyglot\BatchInference\Data\BatchItem;

interface CanEncodeBatchItem
{
    /** @return array<string, mixed> */
    public function encode(BatchItem $item): array;
}
