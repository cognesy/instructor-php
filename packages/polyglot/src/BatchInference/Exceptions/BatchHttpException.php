<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Exceptions;

final class BatchHttpException extends BatchException
{
    public function __construct(private readonly int $statusCode)
    {
        parent::__construct("Batch API returned HTTP {$statusCode}.");
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
