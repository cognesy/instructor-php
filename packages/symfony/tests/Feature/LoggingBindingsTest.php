<?php

declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Event;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted as AgentCtrlExecutionCompleted;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Symfony\DependencyInjection\Configuration;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Cognesy\Instructor\Symfony\InstructorSymfonyBundle;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestLogger;
use Cognesy\Polyglot\Inference\Events\PartialInferenceDeltaCreated;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

function loggingEnabledConfig(array $logging = []): array
{
    return [
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
        'logging' => array_replace_recursive([
            'enabled' => true,
            'preset' => 'custom',
            'level' => 'debug',
        ], $logging),
    ];
}

/** @return list<string> */
function captureDeprecations(callable $callback): array
{
    $messages = [];

    set_error_handler(static function (int $severity, string $message) use (&$messages): bool {
        if ($severity !== E_USER_DEPRECATED) {
            return false;
        }

        $messages[] = $message;

        return true;
    });

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $messages;
}

function compiledSymfonyLoggingContainer(array $config): ContainerBuilder
{
    $container = new ContainerBuilder;
    $container->register('logger', SymfonyTestLogger::class)->setPublic(true);

    $bundle = new InstructorSymfonyBundle;
    $bundle->build($container);

    $extension = new InstructorSymfonyExtension;
    $extension->load([$config], $container);
    $container->compile();

    return $container;
}

it('rejects invalid logging preset values', function (): void {
    $processor = new Processor;
    $processor->processConfiguration(new Configuration, [[
        'logging' => [
            'preset' => 'verbose',
        ],
    ]]);
})->throws(InvalidConfigurationException::class);

it('accepts the deprecated default logging preset alias and emits a deprecation', function (): void {
    $messages = captureDeprecations(static function (): void {
        compiledSymfonyLoggingContainer(loggingEnabledConfig([
            'preset' => 'default',
        ]));
    });

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('instructor.logging.preset="default"')
        ->and($messages[0])->toContain('"development"');
});

it('registers Symfony-owned logging services and wires them into the event bus', function (): void {
    $container = compiledSymfonyLoggingContainer(loggingEnabledConfig());
    expect($container->getParameter('instructor.logging.event_bus_service'))->toBe(CanHandleEvents::class);

    $eventBusDefinition = $container->findDefinition(CanHandleEvents::class);
    $wiretapCalls = array_values(array_filter(
        $eventBusDefinition->getMethodCalls(),
        static fn (array $call): bool => $call[0] === 'wiretap',
    ));

    $loggingWiretap = array_values(array_filter(
        $wiretapCalls,
        static function (array $call): bool {
            $listener = $call[1][0] ?? null;

            if ($listener instanceof Reference) {
                return (string) $listener === 'instructor.logging.pipeline_listener';
            }

            return $listener instanceof Definition
                && $listener->getClass() === \Cognesy\Logging\Integrations\EventPipelineWiretap::class;
        },
    ));

    expect($loggingWiretap)->toHaveCount(1);
});

it('emits logs through the package-owned event bus when instructor.logging is enabled', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $logger = $app->service('logger');

            $events->dispatch(new Event(['probe' => 'enabled']));

            expect($logger)->toBeInstanceOf(SymfonyTestLogger::class)
                ->and($logger->records)->toHaveCount(1)
                ->and($logger->records[0]['message'])->toBe('Event');
        },
        instructorConfig: loggingEnabledConfig(),
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('logger', (new Definition(SymfonyTestLogger::class))->setPublic(true));
            },
        ],
    );
});

it('uses the development preset for low-noise runtime logging defaults', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $logger = $app->service('logger');

            $events->dispatch(new PartialInferenceDeltaCreated);
            $events->dispatch(new AgentExecutionStarted(
                agentId: 'agent-1',
                executionId: 'exec-1',
                parentAgentId: null,
                messageCount: 3,
                availableTools: 2,
            ));
            $events->dispatch(new AgentCtrlExecutionCompleted(
                agentType: AgentType::Codex,
                executionId: AgentCtrlExecutionId::fresh(),
                exitCode: 0,
                toolCallCount: 4,
            ));

            expect($logger)->toBeInstanceOf(SymfonyTestLogger::class)
                ->and($logger->records)->toHaveCount(2)
                ->and(array_column($logger->records, 'message'))->toBe([
                    'Native agent agent-1 started with 3 messages and 2 tools',
                    'Code agent codex completed with exit code 0',
                ]);
        },
        instructorConfig: [
            ...loggingEnabledConfig(),
            'logging' => [
                'enabled' => true,
                'preset' => 'development',
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('logger', (new Definition(SymfonyTestLogger::class))->setPublic(true));
            },
        ],
    );
});

it('uses the production preset to suppress noisy partial-response events', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $logger = $app->service('logger');

            $event = new PartialResponseGenerated(['step' => 1]);
            $event->logLevel = 'warning';

            $events->dispatch($event);

            expect($logger)->toBeInstanceOf(SymfonyTestLogger::class)
                ->and($logger->records)->toHaveCount(0);
        },
        instructorConfig: [
            ...loggingEnabledConfig(),
            'logging' => [
                'enabled' => true,
                'preset' => 'production',
            ],
        ],
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('logger', (new Definition(SymfonyTestLogger::class))->setPublic(true));
            },
        ],
    );
});

it('merges telemetry correlation into log context while preserving framework request precedence', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var RequestStack $requestStack */
            $requestStack = $app->service('request_stack');
            $request = Request::create('https://example.test/orders/42', 'POST');
            $request->headers->set('X-Request-ID', 'framework-req');
            $request->attributes->set('_route', 'orders_show');
            $requestStack->push($request);

            $events = $app->service(CanHandleEvents::class);
            $logger = $app->service('logger');

            $events->dispatch(new Event([
                'agentId' => 'agent-1',
                'executionId' => 'exec-1',
                'telemetry' => [
                    'correlation' => [
                        'root_operation_id' => 'root-1',
                        'parent_operation_id' => 'parent-1',
                        'session_id' => 'session-telemetry',
                        'user_id' => 'user-telemetry',
                        'request_id' => 'telemetry-req',
                    ],
                    'trace' => [
                        'traceparent' => '00-1234567890abcdef1234567890abcdef-1234567890abcdef-01',
                        'tracestate' => 'vendor=value',
                    ],
                ],
            ]));

            expect($logger)->toBeInstanceOf(SymfonyTestLogger::class)
                ->and($logger->records)->toHaveCount(1)
                ->and($logger->records[0]['context']['framework'])->toMatchArray([
                    'request_id' => 'framework-req',
                    'session_id' => 'session-telemetry',
                    'route' => 'orders_show',
                    'method' => 'POST',
                    'url' => 'https://example.test/orders/42',
                    'root_operation_id' => 'root-1',
                    'parent_operation_id' => 'parent-1',
                    'agent_id' => 'agent-1',
                    'execution_id' => 'exec-1',
                    'traceparent' => '00-1234567890abcdef1234567890abcdef-1234567890abcdef-01',
                    'tracestate' => 'vendor=value',
                ])
                ->and($logger->records[0]['context']['user'])->toMatchArray([
                    'user_id' => 'user-telemetry',
                ]);
        },
        instructorConfig: loggingEnabledConfig(),
        containerConfigurators: [
            static function (ContainerBuilder $container): void {
                $container->setDefinition('logger', (new Definition(SymfonyTestLogger::class))->setPublic(true));
            },
        ],
    );
});

it('does not attach the logging pipeline when instructor.logging is disabled', function (): void {
    $container = compiledSymfonyLoggingContainer(loggingEnabledConfig([
        'enabled' => false,
    ]));

    expect($container->hasDefinition('instructor.logging.pipeline_factory'))->toBeFalse()
        ->and($container->hasDefinition('instructor.logging.pipeline_listener'))->toBeFalse();
});
