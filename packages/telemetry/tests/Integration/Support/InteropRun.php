<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Tests\Integration\Support;

final readonly class InteropRun
{
    public function __construct(
        private string $id,
    ) {}

    public static function fresh(string $prefix): self
    {
        $timestamp = gmdate('YmdHis');
        $suffix = bin2hex(random_bytes(4));

        return new self(
            id: "{$prefix}.{$timestamp}.{$suffix}",
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function marker(string $prefix): string
    {
        return "{$prefix} {$this->id}";
    }

    public function serviceName(string $prefix): string
    {
        return "{$prefix}.{$this->id}";
    }
}
