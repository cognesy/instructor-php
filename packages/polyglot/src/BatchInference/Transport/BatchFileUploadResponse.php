<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

final readonly class BatchFileUploadResponse
{
    public function __construct(private int $statusCode, private string $body)
    {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
    public function body(): string
    {
        return $this->body;
    }
}
