<?php declare(strict_types=1);

use Cognesy\Telemetry\Adapters\Langfuse\LangfuseConfig;
use Cognesy\Telemetry\Adapters\Langfuse\LangfuseHttpTransport;
use Cognesy\Telemetry\Adapters\OTel\OtelConfig;
use Cognesy\Telemetry\Adapters\OTel\OtelHttpTransport;
use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportException;
use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportResponse;
use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportResponseException;

it('accepts successful otel transport responses', function () {
    $received = [];

    $transport = new OtelHttpTransport(
        config: new OtelConfig(
            endpoint: 'https://logfire-eu.pydantic.dev',
            headers: ['Authorization' => 'test-token'],
        ),
        sender: function (string $url, array $headers, string $body) use (&$received): HttpTransportResponse {
            $received = [$url, $headers, $body];

            return new HttpTransportResponse(200, '{}');
        },
    );

    $transport->send('traces', ['resourceSpans' => []]);

    expect($received[0] ?? null)->toBe('https://logfire-eu.pydantic.dev/v1/traces');
});

it('throws on otel 4xx responses instead of ignoring them', function () {
    $transport = new OtelHttpTransport(
        config: new OtelConfig(
            endpoint: 'https://logfire-eu.pydantic.dev',
            headers: ['Authorization' => 'test-token'],
        ),
        sender: fn(string $url, array $headers, string $body): HttpTransportResponse => new HttpTransportResponse(
            401,
            '{"error":"invalid token"}',
        ),
    );

    expect(fn() => $transport->send('traces', ['resourceSpans' => []]))
        ->toThrow(
            HttpTransportResponseException::class,
            'Telemetry transport request to https://logfire-eu.pydantic.dev/v1/traces failed with HTTP 401 Client Error',
        );
});

it('throws on otel 5xx responses instead of ignoring them', function () {
    $transport = new OtelHttpTransport(
        config: new OtelConfig(
            endpoint: 'https://logfire-eu.pydantic.dev',
            headers: ['Authorization' => 'test-token'],
        ),
        sender: fn(string $url, array $headers, string $body): HttpTransportResponse => new HttpTransportResponse(
            503,
            'temporarily unavailable',
        ),
    );

    expect(fn() => $transport->send('metrics', ['resourceMetrics' => []]))
        ->toThrow(
            HttpTransportResponseException::class,
            'Telemetry transport request to https://logfire-eu.pydantic.dev/v1/metrics failed with HTTP 503 Server Error',
        );
});

it('throws on langfuse 4xx responses instead of ignoring them', function () {
    $transport = new LangfuseHttpTransport(
        config: new LangfuseConfig(
            baseUrl: 'https://cloud.langfuse.com',
            publicKey: 'pk',
            secretKey: 'sk',
        ),
        sender: fn(string $url, array $headers, string $body): HttpTransportResponse => new HttpTransportResponse(
            400,
            '{"error":{"message":"bad payload"}}',
        ),
    );

    expect(fn() => $transport->send('traces', ['resourceSpans' => []]))
        ->toThrow(
            HttpTransportResponseException::class,
            'Telemetry transport request to https://cloud.langfuse.com/api/public/otel/v1/traces failed with HTTP 400 Client Error',
        );
});

it('includes the request url when the transport cannot obtain an http status', function () {
    $transport = new OtelHttpTransport(
        config: new OtelConfig(
            endpoint: 'http://127.0.0.1:1',
            headers: [],
        ),
    );

    expect(fn() => $transport->send('traces', ['resourceSpans' => []]))
        ->toThrow(HttpTransportException::class, 'http://127.0.0.1:1/v1/traces');
});
