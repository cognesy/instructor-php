<?php

declare(strict_types=1);

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
use Cognesy\Instructor\Symfony\Tests\Support\MockHttpClientFactory;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
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

it('registers the baseline core contracts and entrypoint services', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $config = $app->service(CanProvideConfig::class);
            $events = $app->service(CanHandleEvents::class);
            $http = $app->service(CanSendHttpRequests::class);
            $inferenceRuntime = $app->service(CanCreateInference::class);
            $embeddingsRuntime = $app->service(CanCreateEmbeddings::class);
            $structuredRuntime = $app->service(CanCreateStructuredOutput::class);

            expect($config)->toBeInstanceOf(SymfonyConfigProvider::class);
            expect($config->get('llm.default'))->toBe('openai');
            expect($events)->toBeInstanceOf(EventDispatcher::class);
            expect($http)->toBeInstanceOf(HttpClient::class);
            expect($http->config()->driver)->toBe('symfony');
            expect($inferenceRuntime)->toBeInstanceOf(InferenceRuntime::class);
            expect($embeddingsRuntime)->toBeInstanceOf(EmbeddingsRuntime::class);
            expect($structuredRuntime)->toBeInstanceOf(StructuredOutputRuntime::class);
            expect($app->service(Inference::class))->toBeInstanceOf(Inference::class);
            expect($app->service(Embeddings::class))->toBeInstanceOf(Embeddings::class);
            expect($app->service(StructuredOutput::class))->toBeInstanceOf(StructuredOutput::class);
        },
        instructorConfig: [
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
    ]);
});

it('uses the framework http client service for the symfony transport', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $client = $app->service(CanSendHttpRequests::class);
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
        },
        instructorConfig: [
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
    ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('http_client', (new Definition(MockHttpClient::class))
                    ->setFactory([MockHttpClientFactory::class, 'withResponse'])
                    ->setArguments(['framework-response', 200]));
            },
        ],
    );
});

it('keeps explicit non-symfony driver selection intact', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $client = $app->service(CanSendHttpRequests::class);

            expect($client)->toBeInstanceOf(HttpClient::class)
                ->and($client->config()->driver)->toBe('curl');
        },
        instructorConfig: [
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
    ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('http_client', new Definition(MockHttpClient::class));
            },
        ],
    );
});

it('bridges the package-owned event bus into symfony and runtime services', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $frameworkCalls = 0;
            $runtimeCalls = 0;
            $builtCalls = 0;

            $frameworkDispatcher = $app->service('event_dispatcher');
            $frameworkDispatcher->addListener(
                SymfonyBridgeProbeEvent::class,
                static function () use (&$frameworkCalls): void {
                    $frameworkCalls++;
                },
            );

            $events = $app->service(CanHandleEvents::class);
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
            $app->service(CanSendHttpRequests::class);

            expect($frameworkCalls)->toBe(1)
                ->and($runtimeCalls)->toBe(1)
                ->and($builtCalls)->toBe(1);
        },
        instructorConfig: [
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
    ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));
            },
        ],
    );
});

it('forwards parent and interface listeners through the symfony bridge', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $parentCalls = 0;
            $interfaceCalls = 0;

            $frameworkDispatcher = $app->service('event_dispatcher');
            $frameworkDispatcher->addListener(
                SymfonyBridgeParentProbeEvent::class,
                static function () use (&$parentCalls): void {
                    $parentCalls++;
                },
            );
            $frameworkDispatcher->addListener(
                SymfonyBridgeTaggedProbe::class,
                static function () use (&$interfaceCalls): void {
                    $interfaceCalls++;
                },
            );

            $events = $app->service(CanHandleEvents::class);
            $events->dispatch(new SymfonyBridgeChildProbeEvent);

            expect($parentCalls)->toBe(1)
                ->and($interfaceCalls)->toBe(1);
        },
        instructorConfig: [
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
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));
            },
        ],
    );
});

it('can disable framework event bridging while keeping the package bus active', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $frameworkCalls = 0;
            $runtimeCalls = 0;

            $frameworkDispatcher = $app->service('event_dispatcher');
            $frameworkDispatcher->addListener(
                SymfonyBridgeProbeEvent::class,
                static function () use (&$frameworkCalls): void {
                    $frameworkCalls++;
                },
            );

            $events = $app->service(CanHandleEvents::class);
            $events->addListener(
                SymfonyBridgeProbeEvent::class,
                static function () use (&$runtimeCalls): void {
                    $runtimeCalls++;
                },
            );

            $events->dispatch(new SymfonyBridgeProbeEvent);

            expect($runtimeCalls)->toBe(1)
                ->and($frameworkCalls)->toBe(0);
        },
        instructorConfig: [
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
    ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));
            },
        ],
    );
});

it('honors the legacy bridge_to_symfony alias when dispatch_to_symfony is omitted', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $frameworkCalls = 0;
            $runtimeCalls = 0;

            $frameworkDispatcher = $app->service('event_dispatcher');
            $frameworkDispatcher->addListener(
                SymfonyBridgeProbeEvent::class,
                static function () use (&$frameworkCalls): void {
                    $frameworkCalls++;
                },
            );

            $events = $app->service(CanHandleEvents::class);
            $events->addListener(
                SymfonyBridgeProbeEvent::class,
                static function () use (&$runtimeCalls): void {
                    $runtimeCalls++;
                },
            );

            $events->dispatch(new SymfonyBridgeProbeEvent);

            expect($runtimeCalls)->toBe(1)
                ->and($frameworkCalls)->toBe(0);
        },
        instructorConfig: [
            'connections' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4o-mini',
                ],
            ],
            'events' => [
                'bridge_to_symfony' => false,
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('event_dispatcher', (new Definition(SymfonyFrameworkEventDispatcher::class))->setPublic(true));
            },
        ],
    );
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

interface SymfonyBridgeTaggedProbe {}

class SymfonyBridgeParentProbeEvent {}

final class SymfonyBridgeChildProbeEvent extends SymfonyBridgeParentProbeEvent implements SymfonyBridgeTaggedProbe {}

final class SymfonyBridgeProbeEvent {}
