<?php

declare(strict_types=1);

require_once __DIR__.'/../../src/Support/SymfonyConfigProvider.php';

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Symfony\Support\SymfonyConfigProvider;

it('provides normalized framework config views and typed runtime objects', function (): void {
    $provider = new SymfonyConfigProvider([
        'connections' => [
            'default' => 'anthropic',
            'items' => [
                'anthropic' => [
                    'api_key' => 'anthropic-key',
                    'model' => 'claude-sonnet-4',
                    'api_version' => '2023-06-01',
                    'beta' => 'tools-2024-04-04',
                ],
                'azure' => [
                    'driver' => 'azure',
                    'resource_name' => 'workspace',
                    'deployment_id' => 'gpt-4o-mini',
                    'api_version' => '2024-08-01-preview',
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.2,
                    'options' => ['presence_penalty' => 0.1],
                ],
            ],
        ],
        'embeddings' => [
            'default' => 'azure-embed',
            'connections' => [
                'azure-embed' => [
                    'driver' => 'azure',
                    'resource_name' => 'workspace',
                    'deployment_id' => 'text-embedding-3-small',
                    'api_version' => '2024-08-01-preview',
                    'model' => 'text-embedding-3-small',
                    'dimensions' => '1536',
                ],
            ],
        ],
        'extraction' => [
            'output_mode' => 'tools',
            'max_retries' => '2',
            'retry_prompt' => 'Fix the payload',
            'tool_name' => 'extract_contact',
            'default_to_std_class' => true,
        ],
        'http' => [
            'driver' => 'symfony',
            'timeout' => '20',
            'connect_timeout' => '5',
            'fail_on_error' => 'true',
            'stream_chunk_size' => '1024',
        ],
    ]);

    expect($provider->has('llm.connections.anthropic'))->toBeTrue()
        ->and($provider->get('llm.default'))->toBe('anthropic')
        ->and($provider->get('embed.default'))->toBe('azure-embed')
        ->and($provider->get('http.connections.default.driver'))->toBe('symfony')
        ->and($provider->get('structured.outputMode'))->toBe('tool_call')
        ->and($provider->get('instructor.extraction.output_mode'))->toBe('tools');

    $llm = $provider->llm();
    $azure = $provider->llm('azure');
    $embeddings = $provider->embeddings();
    $structured = $provider->structuredOutput();
    $http = $provider->httpClient();

    expect($llm->driver)->toBe('anthropic')
        ->and($llm->apiUrl)->toBe('https://api.anthropic.com/v1')
        ->and($llm->endpoint)->toBe('/messages')
        ->and($llm->metadata)->toBe([
            'apiVersion' => '2023-06-01',
            'beta' => 'tools-2024-04-04',
        ])
        ->and($azure->apiUrl)->toBe('https://{resourceName}.openai.azure.com/openai/deployments/{deploymentId}')
        ->and($azure->metadata)->toBe([
            'resourceName' => 'workspace',
            'deploymentId' => 'gpt-4o-mini',
            'apiVersion' => '2024-08-01-preview',
        ])
        ->and($azure->options)->toBe([
            'temperature' => 0.2,
            'presence_penalty' => 0.1,
        ])
        ->and($embeddings->driver)->toBe('azure')
        ->and($embeddings->dimensions)->toBe(1536)
        ->and($structured->outputMode())->toBe(OutputMode::Tools)
        ->and($structured->maxRetries())->toBe(2)
        ->and($structured->toolName())->toBe('extract_contact')
        ->and($structured->defaultToStdClass())->toBeTrue()
        ->and($http->driver)->toBe('symfony')
        ->and($http->requestTimeout)->toBe(20)
        ->and($http->connectTimeout)->toBe(5)
        ->and($http->streamChunkSize)->toBe(1024)
        ->and($http->failOnError)->toBeTrue();
});

it('supports flat connection maps and legacy preset aliases', function (): void {
    $provider = new SymfonyConfigProvider([
        'default' => 'openai',
        'connections' => [
            'openai' => [
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
                'organization' => 'acme',
            ],
        ],
        'embeddings' => [
            'openai' => [
                'model' => 'text-embedding-3-small',
                'default_dimensions' => '1536',
            ],
        ],
    ]);

    expect($provider->get('llm.defaultPreset'))->toBe('openai')
        ->and($provider->get('llm.presets.openai.model'))->toBe('gpt-4o-mini')
        ->and($provider->llm()->metadata)->toBe(['organization' => 'acme'])
        ->and($provider->embeddings()->dimensions)->toBe(1536)
        ->and($provider->httpClient()->driver)->toBe('symfony');
});
