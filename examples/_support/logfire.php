<?php declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

function exampleLogfireHub(string $serviceName): Telemetry
{
    return new Telemetry(
        registry: new TraceRegistry(),
        exporter: exampleLogfireExporter($serviceName),
    );
}

function exampleLogfireExporter(string $serviceName): LogfireExporter
{
    $token = (string) Env::get('LOGFIRE_TOKEN', '');
    if ($token === '') {
        $token = (string) Env::get('LOGFIRE_API_TOKEN', '');
    }

    if ($token === '') {
        throw new RuntimeException('Set LOGFIRE_TOKEN in .env to run this example.');
    }

    $endpoint = (string) Env::get('LOGFIRE_OTLP_ENDPOINT', '');
    if ($endpoint === '') {
        $endpoint = (string) Env::get('LOGFIRE_BASE_URL', exampleLogfireBaseUrl($token));
    }

    $normalizedEndpoint = preg_replace('#/v1/(traces|metrics)$#', '', $endpoint) ?: $endpoint;

    return new LogfireExporter(new LogfireConfig(
        endpoint: rtrim($normalizedEndpoint, '/'),
        serviceName: $serviceName,
        headers: ['Authorization' => $token],
    ));
}

function exampleLogfireBaseUrl(string $token): string
{
    if (preg_match('/^pylf_v\d+_([a-z]+)_/', $token, $matches) !== 1) {
        return 'https://logfire-us.pydantic.dev';
    }

    return match ($matches[1]) {
        'eu' => 'https://logfire-eu.pydantic.dev',
        default => 'https://logfire-us.pydantic.dev',
    };
}
