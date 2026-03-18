<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Infrastructure\Transport;

final class HttpTransportResponseException extends HttpTransportException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        string $message,
    ) {
        parent::__construct($message);
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
