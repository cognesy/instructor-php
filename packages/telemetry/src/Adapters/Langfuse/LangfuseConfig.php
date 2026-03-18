<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\Langfuse;

final readonly class LangfuseConfig
{
    public function __construct(
        private string $baseUrl,
        private string $publicKey,
        private string $secretKey,
    ) {}

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }
}
