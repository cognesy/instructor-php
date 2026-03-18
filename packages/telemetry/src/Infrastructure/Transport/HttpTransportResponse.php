<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Infrastructure\Transport;

final readonly class HttpTransportResponse
{
    public function __construct(
        private int $statusCode,
        private string $body,
    ) {}

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }
}
