<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Tests\Integration\Support;

use Cognesy\Config\Env;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseHttpTransport;
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

final class InteropTelemetryFactory
{
    public static function logfire(string $serviceName): Telemetry
    {
        return new Telemetry(
            registry: new TraceRegistry(),
            exporter: new LogfireExporter(new LogfireConfig(
                endpoint: rtrim((string) Env::get('LOGFIRE_OTLP_ENDPOINT', ''), '/'),
                serviceName: $serviceName,
                headers: ['Authorization' => (string) Env::get('LOGFIRE_TOKEN', '')],
            )),
        );
    }

    public static function langfuse(): Telemetry
    {
        return new Telemetry(
            registry: new TraceRegistry(),
            exporter: new LangfuseExporter(
                transport: new LangfuseHttpTransport(new LangfuseConfig(
                    baseUrl: (string) Env::get('LANGFUSE_BASE_URL', ''),
                    publicKey: (string) Env::get('LANGFUSE_PUBLIC_KEY', ''),
                    secretKey: (string) Env::get('LANGFUSE_SECRET_KEY', ''),
                )),
            ),
        );
    }
}
