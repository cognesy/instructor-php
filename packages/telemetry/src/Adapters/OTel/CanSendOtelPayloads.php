<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\OTel;

interface CanSendOtelPayloads
{
    /** @param array<string, mixed> $payload */
    public function send(string $signal, array $payload): void;
}
