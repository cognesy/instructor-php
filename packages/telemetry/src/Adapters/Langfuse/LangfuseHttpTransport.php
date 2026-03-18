<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\Langfuse;

use Cognesy\Telemetry\Adapters\OTel\CanSendOtelPayloads;
use Cognesy\Telemetry\Adapters\OTel\OtelConfig;
use Cognesy\Telemetry\Adapters\OTel\OtelHttpTransport;
use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportResponse;

final readonly class LangfuseHttpTransport implements CanSendOtelPayloads
{
    /**
     * @param null|\Closure(string, array<string, bool|float|int|string>, string): HttpTransportResponse $sender
     */
    public function __construct(
        private LangfuseConfig $config,
        private ?\Closure $sender = null,
    ) {}

    #[\Override]
    public function send(string $signal, array $payload): void
    {
        $this->otelTransport()->send($signal, $payload);
    }

    private function otelTransport(): OtelHttpTransport
    {
        return new OtelHttpTransport(
            config: new OtelConfig(
                endpoint: $this->config->baseUrl() . '/api/public/otel',
                headers: [
                    'Authorization' => 'Basic ' . base64_encode(
                        $this->config->publicKey() . ':' . $this->config->secretKey()
                    ),
                    'x-langfuse-public-key' => $this->config->publicKey(),
                ],
            ),
            sender: $this->sender,
        );
    }
}
