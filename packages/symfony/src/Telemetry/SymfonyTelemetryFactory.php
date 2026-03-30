<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Telemetry;

use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetryProjector;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseExporter;
use Cognesy\Telemetry\Adapters\Logfire\LogfireConfig;
use Cognesy\Telemetry\Adapters\Logfire\LogfireExporter;
use Cognesy\Telemetry\Adapters\OTel\OtelConfig;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Adapters\OTel\OtelHttpTransport;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;

final class SymfonyTelemetryFactory
{
    /** @param array<string, mixed> $config */
    public function exporter(array $config): CanExportObservations
    {
        if (! $this->enabled($config)) {
            return new NullTelemetryExporter;
        }

        return match ($this->driver($config)) {
            'otel' => $this->otelExporter($config),
            'langfuse' => $this->langfuseExporter($config),
            'logfire' => $this->logfireExporter($config),
            'composite' => $this->compositeExporter($config),
            default => new NullTelemetryExporter,
        };
    }

    /** @param array<string, mixed> $config */
    public function projector(Telemetry $telemetry, array $config): CanProjectTelemetry
    {
        return new CompositeTelemetryProjector($this->projectors($telemetry, $config));
    }

    /** @param array<string, mixed> $config */
    private function otelExporter(array $config): CanExportObservations
    {
        $driver = $this->driverConfig($config, 'otel');
        $endpoint = $this->stringValue($driver, 'endpoint');

        if ($endpoint === '') {
            return new NullTelemetryExporter;
        }

        return new OtelExporter(
            transport: new OtelHttpTransport(new OtelConfig(
                endpoint: $endpoint,
                serviceName: $this->serviceName($config),
                headers: $this->stringMap($driver['headers'] ?? []),
            )),
        );
    }

    /** @param array<string, mixed> $config */
    private function langfuseExporter(array $config): CanExportObservations
    {
        $driver = $this->driverConfig($config, 'langfuse');
        $host = $this->stringValue($driver, 'host');
        $publicKey = $this->stringValue($driver, 'public_key');
        $secretKey = $this->stringValue($driver, 'secret_key');

        if ($host === '' || $publicKey === '' || $secretKey === '') {
            return new NullTelemetryExporter;
        }

        return new LangfuseExporter(
            config: new LangfuseConfig(
                baseUrl: $host,
                publicKey: $publicKey,
                secretKey: $secretKey,
            ),
        );
    }

    /** @param array<string, mixed> $config */
    private function logfireExporter(array $config): CanExportObservations
    {
        $driver = $this->driverConfig($config, 'logfire');
        $endpoint = $this->stringValue($driver, 'endpoint');
        $writeToken = $this->stringValue($driver, 'write_token');

        if ($endpoint === '' || $writeToken === '') {
            return new NullTelemetryExporter;
        }

        $headers = $this->stringMap($driver['headers'] ?? []);
        $headers['Authorization'] = $writeToken;

        return new LogfireExporter(new LogfireConfig(
            endpoint: $endpoint,
            serviceName: $this->serviceName($config),
            headers: $headers,
        ));
    }

    /** @param array<string, mixed> $config */
    private function compositeExporter(array $config): CanExportObservations
    {
        $drivers = $this->arrayValue($this->driverConfig($config, 'composite'), 'exporters');
        $exporters = [];

        foreach ($drivers as $driver) {
            if (! is_string($driver) || $driver === '') {
                continue;
            }

            $exporter = match ($driver) {
                'otel' => $this->otelExporter($config),
                'langfuse' => $this->langfuseExporter($config),
                'logfire' => $this->logfireExporter($config),
                default => new NullTelemetryExporter,
            };

            if ($exporter instanceof NullTelemetryExporter) {
                continue;
            }

            $exporters[] = $exporter;
        }

        return new CompositeTelemetryExporter($exporters);
    }

    /** @param array<string, mixed> $config */
    private function projectors(Telemetry $telemetry, array $config): array
    {
        if (! $this->enabled($config)) {
            return [];
        }

        $projectors = [];
        $selection = $this->arrayValue($config, 'projectors');

        foreach ($selection as $key => $enabled) {
            if ($enabled !== true || ! is_string($key)) {
                continue;
            }

            $projector = match ($key) {
                'instructor' => new InstructorTelemetryProjector($telemetry),
                'polyglot' => new PolyglotTelemetryProjector($telemetry),
                'http' => new HttpClientTelemetryProjector(
                    telemetry: $telemetry,
                    captureStreamingChunks: $this->httpChunkCaptureEnabled($config),
                ),
                'agent_ctrl' => new AgentCtrlTelemetryProjector($telemetry),
                'agents' => new AgentsTelemetryProjector($telemetry),
                default => null,
            };

            if ($projector === null) {
                continue;
            }

            $projectors[] = $projector;
        }

        return $projectors;
    }

    /** @param array<string, mixed> $config */
    private function enabled(array $config): bool
    {
        return ($config['enabled'] ?? false) === true;
    }

    /** @param array<string, mixed> $config */
    private function httpChunkCaptureEnabled(array $config): bool
    {
        return ($this->arrayValue($config, 'http')['capture_streaming_chunks'] ?? false) === true;
    }

    /** @param array<string, mixed> $config */
    private function driver(array $config): string
    {
        $driver = $config['driver'] ?? 'null';

        return is_string($driver) && $driver !== '' ? $driver : 'null';
    }

    /** @param array<string, mixed> $config */
    private function serviceName(array $config): string
    {
        return $this->stringValue($config, 'service_name', 'symfony');
    }

    /** @param array<string, mixed> $config */
    private function driverConfig(array $config, string $driver): array
    {
        return $this->arrayValue($this->arrayValue($config, 'drivers'), $driver);
    }

    /** @param array<string, mixed> $value */
    private function arrayValue(array $value, string $key): array
    {
        $candidate = $value[$key] ?? [];

        return is_array($candidate) ? $candidate : [];
    }

    /** @param array<string, mixed> $value */
    private function stringValue(array $value, string $key, string $default = ''): string
    {
        $candidate = $value[$key] ?? null;

        return is_string($candidate) && $candidate !== '' ? $candidate : $default;
    }

    /** @return array<string, string> */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_scalar($item)) {
                continue;
            }

            $resolved[$key] = (string) $item;
        }

        return $resolved;
    }
}
