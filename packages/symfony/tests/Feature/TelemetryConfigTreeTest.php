<?php

declare(strict_types=1);

use Cognesy\Instructor\Symfony\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

it('defines a typed telemetry config tree with explicit exporter selection', function (): void {
    $processor = new Processor;
    $config = $processor->processConfiguration(new Configuration, [[
        'telemetry' => [
            'enabled' => true,
            'driver' => 'composite',
            'service_name' => 'symfony-api',
            'projectors' => [
                'http' => false,
                'agents' => true,
            ],
            'http' => [
                'capture_streaming_chunks' => true,
            ],
            'drivers' => [
                'composite' => [
                    'exporters' => ['otel', 'langfuse'],
                ],
                'otel' => [
                    'endpoint' => 'https://otel.example.invalid/v1/traces',
                    'headers' => [
                        'Authorization' => 'Bearer secret',
                    ],
                ],
                'langfuse' => [
                    'host' => 'https://langfuse.example.invalid',
                    'public_key' => 'pk',
                    'secret_key' => 'sk',
                ],
                'logfire' => [
                    'endpoint' => 'https://logfire.example.invalid/v1/traces',
                    'write_token' => 'token',
                    'headers' => [
                        'X-Test' => '1',
                    ],
                ],
            ],
        ],
    ]]);

    expect($config['telemetry'])->toMatchArray([
        'enabled' => true,
        'driver' => 'composite',
        'service_name' => 'symfony-api',
        'projectors' => [
            'instructor' => true,
            'polyglot' => true,
            'http' => false,
            'agent_ctrl' => true,
            'agents' => true,
        ],
        'http' => [
            'capture_streaming_chunks' => true,
        ],
        'drivers' => [
            'composite' => [
                'exporters' => ['otel', 'langfuse'],
            ],
            'otel' => [
                'endpoint' => 'https://otel.example.invalid/v1/traces',
                'headers' => [
                    'Authorization' => 'Bearer secret',
                ],
            ],
            'langfuse' => [
                'host' => 'https://langfuse.example.invalid',
                'public_key' => 'pk',
                'secret_key' => 'sk',
            ],
            'logfire' => [
                'endpoint' => 'https://logfire.example.invalid/v1/traces',
                'write_token' => 'token',
                'headers' => [
                    'X-Test' => '1',
                ],
            ],
        ],
    ]);
});

it('rejects unsupported telemetry drivers and composite exporters', function (): void {
    $processor = new Processor;

    expect(static fn () => $processor->processConfiguration(new Configuration, [[
        'telemetry' => [
            'driver' => 'stdout',
        ],
    ]]))->toThrow(InvalidConfigurationException::class);

    expect(static fn () => $processor->processConfiguration(new Configuration, [[
        'telemetry' => [
            'driver' => 'composite',
            'drivers' => [
                'composite' => [
                    'exporters' => ['stdout'],
                ],
            ],
        ],
    ]]))->toThrow(InvalidConfigurationException::class);
});
