<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\Logfire;

final readonly class LogfireConfig
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $endpoint,
        private string $serviceName = 'instructor-php',
        private array $headers = [],
    ) {}

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
