<?php

declare(strict_types=1);

require_once __DIR__.'/../../src/Support/SymfonyConfigProvider.php';
require_once __DIR__.'/../../src/Support/SymfonyEventBusFactory.php';
require_once __DIR__.'/../../src/Support/SymfonyHttpTransportFactory.php';
require_once __DIR__.'/../../src/DependencyInjection/InstructorSymfonyExtension.php';
require_once __DIR__.'/../../src/DependencyInjection/Configuration.php';

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Symfony\Support\SymfonyConfigProvider;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyFrameworkEventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('registers the baseline core contracts and entrypoint services', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'default' => 'openai',
            'items' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        'embeddings' => [
            'default' => 'openai',
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'model' => 'text-embedding-3-small',
                ],
            ],
        ],
        'extraction' => [
            'output_mode' => 'json_schema',
            'max_retries' => 1,
        ],
        'http' => [
            'driver' => 'symfony',
            'timeout' => 15,
        ],
    ]], $container);

    $container->compile();

    $config = $container->get(CanProvideConfig::class);
    $events = $container->get(CanHandleEvents::class);
    $http = $container->get(CanSendHttpRequests::class);
    $inferenceRuntime = $container->get(CanCreateInference::class);
    $embeddingsRuntime = $container->get(CanCreateEmbeddings::class);
    $structuredRuntime = $container->get(CanCreateStructuredOutput::class);

    expect($config)->toBeInstanceOf(SymfonyConfigProvider::class);
    expect($config->get('llm.default'))->toBe('openai');
    expect($events)->toBeInstanceOf(EventDispatcher::class);
    expect($http)->toBeInstanceOf(HttpClient::class);
    expect($http->config()->driver)->toBe('symfony');
    expect($inferenceRuntime)->toBeInstanceOf(InferenceRuntime::class);
    expect($embeddingsRuntime)->toBeInstanceOf(EmbeddingsRuntime::class);
    expect($structuredRuntime)->toBeInstanceOf(StructuredOutputRuntime::class);
    expect($container->get(Inference::class))->toBeInstanceOf(Inference::class);
    expect($container->get(Embeddings::class))->toBeInstanceOf(Embeddings::class);
    expect($container->get(StructuredOutput::class))->toBeInstanceOf(StructuredOutput::class);
});

it('uses the framework http client service for the symfony transport', function (): void {
    $container = new ContainerBuilder;
    $container->setDefinition('http_client', new Definition(MockHttpClient::class, [[
        new MockResponse('framework-response', ['http_code' => 200]),
    ]]));

    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'http' => [
            'driver' => 'symfony',
        ],
    ]], $container);

    $container->compile();

    $client = $container->get(CanSendHttpRequests::class);
    $response = $client->send(new HttpRequest(
        url: 'https://example.invalid/framework',
        method: 'GET',
        headers: [],
        body: '',
        options: [],
    ))->content();

    expect($client)->toBeInstanceOf(HttpClient::class)
        ->and($client->config()->driver)->toBe('symfony')
        ->and($response)->toBe('framework-response');
});

it('keeps explicit non-symfony driver selection intact', function (): void {
    $container = new ContainerBuilder;
    $container->setDefinition('http_client', new Definition(MockHttpClient::class));

    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'http' => [
            'driver' => 'curl',
        ],
    ]], $container);

    $container->compile();

    $client = $container->get(CanSendHttpRequests::class);

    expect($client)->toBeInstanceOf(HttpClient::class)
        ->and($client->config()->driver)->toBe('curl');
});

it('bridges the package-owned event bus into symfony and runtime services', function (): void {
    $container = new ContainerBuilder;
    $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));

    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'events' => [
            'dispatch_to_symfony' => true,
        ],
    ]], $container);

    $container->compile();

    $frameworkCalls = 0;
    $runtimeCalls = 0;
    $builtCalls = 0;

    $frameworkDispatcher = $container->get('event_dispatcher');
    $frameworkDispatcher->addListener(
        SymfonyBridgeProbeEvent::class,
        static function () use (&$frameworkCalls): void {
            $frameworkCalls++;
        },
    );

    $events = $container->get(CanHandleEvents::class);
    $events->addListener(
        SymfonyBridgeProbeEvent::class,
        static function () use (&$runtimeCalls): void {
            $runtimeCalls++;
        },
    );
    $events->addListener(
        HttpClientBuilt::class,
        static function () use (&$builtCalls): void {
            $builtCalls++;
        },
    );

    $events->dispatch(new SymfonyBridgeProbeEvent);
    $container->get(CanSendHttpRequests::class);

    expect($frameworkCalls)->toBe(1)
        ->and($runtimeCalls)->toBe(1)
        ->and($builtCalls)->toBe(1);
});

it('can disable framework event bridging while keeping the package bus active', function (): void {
    $container = new ContainerBuilder;
    $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));

    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'events' => [
            'dispatch_to_symfony' => false,
        ],
    ]], $container);

    $container->compile();

    $frameworkCalls = 0;
    $runtimeCalls = 0;

    $frameworkDispatcher = $container->get('event_dispatcher');
    $frameworkDispatcher->addListener(
        SymfonyBridgeProbeEvent::class,
        static function () use (&$frameworkCalls): void {
            $frameworkCalls++;
        },
    );

    $events = $container->get(CanHandleEvents::class);
    $events->addListener(
        SymfonyBridgeProbeEvent::class,
        static function () use (&$runtimeCalls): void {
            $runtimeCalls++;
        },
    );

    $events->dispatch(new SymfonyBridgeProbeEvent);

    expect($runtimeCalls)->toBe(1)
        ->and($frameworkCalls)->toBe(0);
});

it('resolves core services in a cli-like container without framework services', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'default' => 'openai',
            'items' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
        ],
        'embeddings' => [
            'default' => 'openai',
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'model' => 'text-embedding-3-small',
                ],
            ],
        ],
        'http' => [
            'driver' => 'framework',
        ],
        'events' => [
            'dispatch_to_symfony' => false,
        ],
    ]], $container);

    $container->compile();

    $calls = 0;
    $events = $container->get(CanHandleEvents::class);
    $events->addListener(
        SymfonyBridgeProbeEvent::class,
        static function () use (&$calls): void {
            $calls++;
        },
    );
    $events->dispatch(new SymfonyBridgeProbeEvent);

    expect($container->has('request_stack'))->toBeFalse()
        ->and($container->has('http_client'))->toBeFalse()
        ->and($container->has('event_dispatcher'))->toBeFalse()
        ->and($container->get(CanProvideConfig::class))->toBeInstanceOf(SymfonyConfigProvider::class)
        ->and($container->get(CanSendHttpRequests::class))->toBeInstanceOf(HttpClient::class)
        ->and($container->get(CanSendHttpRequests::class)->config()->driver)->toBe('symfony')
        ->and($container->get(CanCreateInference::class))->toBeInstanceOf(InferenceRuntime::class)
        ->and($container->get(CanCreateEmbeddings::class))->toBeInstanceOf(EmbeddingsRuntime::class)
        ->and($container->get(CanCreateStructuredOutput::class))->toBeInstanceOf(StructuredOutputRuntime::class)
        ->and($calls)->toBe(1);
});

it('does not require request-scoped services in an http-integrated container', function (): void {
    $container = new ContainerBuilder;
    $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));
    $container->setDefinition('http_client', (new Definition(MockHttpClient::class))->setPublic(true));

    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        'http' => [
            'driver' => 'symfony',
        ],
        'events' => [
            'dispatch_to_symfony' => true,
        ],
    ]], $container);

    $container->compile();

    expect($container->has('request_stack'))->toBeFalse()
        ->and($container->get(CanProvideConfig::class))->toBeInstanceOf(SymfonyConfigProvider::class)
        ->and($container->get(CanHandleEvents::class))->toBeInstanceOf(EventDispatcher::class)
        ->and($container->get(CanSendHttpRequests::class))->toBeInstanceOf(HttpClient::class)
        ->and($container->get(CanCreateInference::class))->toBeInstanceOf(InferenceRuntime::class)
        ->and($container->get(CanCreateEmbeddings::class))->toBeInstanceOf(EmbeddingsRuntime::class)
        ->and($container->get(CanCreateStructuredOutput::class))->toBeInstanceOf(StructuredOutputRuntime::class);
});

it('rejects invalid connections subtree shapes during configuration processing', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $load = static fn () => $extension->load([[
        'connections' => 'openai',
    ]], $container);

    expect($load)->toThrow(\InvalidArgumentException::class, 'instructor.connections');
});

it('rejects invalid flat connection entries during configuration processing', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $load = static fn () => $extension->load([[
        'connections' => [
            'openai' => 'not-an-array',
        ],
    ]], $container);

    expect($load)->toThrow(InvalidConfigurationException::class, 'instructor.connections.openai');
});

it('rejects invalid http and events subtree shapes during configuration processing', function (string $subtree): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $load = static fn () => $extension->load([[
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
        $subtree => 'invalid',
    ]], $container);

    expect($load)->toThrow(\InvalidArgumentException::class, "instructor.{$subtree}");
})->with(['http', 'events']);

final class SymfonyBridgeProbeEvent {}
