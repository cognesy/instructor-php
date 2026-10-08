<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use InvalidArgumentException;

final readonly class BatchJobId
{
    public function __construct(private string $value)
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Batch job ID must be a nonempty opaque identifier.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
