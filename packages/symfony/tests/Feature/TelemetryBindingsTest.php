<?php

declare(strict_types=1);

use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;
use Cognesy\Instructor\Symfony\InstructorSymfonyBundle;
use Cognesy\Instructor\Symfony\Telemetry\NullTelemetryExporter;
use Cognesy\Instructor\Symfony\Tests\Support\RecordingTelemetryExporter;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTelemetryServiceOverrides;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Projector\CanProjectTelemetry;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Contract\CanExportObservations;
use Cognesy\Telemetry\Domain\Contract\CanFlushTelemetry;
use Cognesy\Telemetry\Domain\Contract\CanShutdownTelemetry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;

it('registers telemetry services with a predictable null-export path when disabled', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            expect($app->service(CanExportObservations::class))->toBeInstanceOf(NullTelemetryExporter::class)
                ->and($app->service(Telemetry::class))->toBeInstanceOf(Telemetry::class)
                ->and($app->service(CanFlushTelemetry::class))->toBeInstanceOf(Telemetry::class)
                ->and($app->service(CanShutdownTelemetry::class))->toBeInstanceOf(Telemetry::class)
                ->and($app->service(CanProjectTelemetry::class))->toBeInstanceOf(CompositeTelemetryProjector::class)
                ->and($app->service(RuntimeEventBridge::class))->toBeInstanceOf(RuntimeEventBridge::class);
        },
        instructorConfig: telemetryTestConfig(),
    );
});

it('wires the telemetry observation bridge into the package-owned event bus', function (): void {
    $container = compiledSymfonyTelemetryContainer(telemetryTestConfig([
        'telemetry' => [
            'enabled' => true,
        ],
    ]));

    $eventBusDefinition = $container->findDefinition(CanHandleEvents::class);
    $wiretapCalls = array_values(array_filter(
        $eventBusDefinition->getMethodCalls(),
        static fn (array $call): bool => $call[0] === 'wiretap',
    ));

    $telemetryWiretap = array_values(array_filter(
        $wiretapCalls,
        static function (array $call): bool {
            $listener = $call[1][0] ?? null;

            if ($listener instanceof Reference) {
                return (string) $listener === 'instructor.telemetry.observation_bridge';
            }

            return $listener instanceof Definition
                && $listener->getClass() === \Cognesy\Instructor\Symfony\Telemetry\TelemetryObservationBridge::class;
        },
    ));

    expect($telemetryWiretap)->toHaveCount(1);
});

it('projects configured runtime events into the selected telemetry exporter', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $exporter = $app->service(CanExportObservations::class);

            expect($exporter)->toBeInstanceOf(OtelExporter::class);

            $events->dispatch(new AgentExecutionStarted(
                agentId: 'agent-telemetry',
                executionId: 'exec-telemetry',
                parentAgentId: null,
                messageCount: 2,
                availableTools: 1,
            ));
            $events->dispatch(new AgentExecutionCompleted(
                agentId: 'agent-telemetry',
                executionId: 'exec-telemetry',
                parentAgentId: null,
                status: \Cognesy\Agents\Enums\ExecutionStatus::Completed,
                totalSteps: 1,
                totalUsage: new InferenceUsage(inputTokens: 3, outputTokens: 5),
                errors: null,
            ));

            expect($exporter->observations())->toHaveCount(1)
                ->and($exporter->observations()[0]->name())->toBe('agent.execute')
                ->and($exporter->observations()[0]->attributes()->toArray()['agent.total_steps'] ?? null)->toBe(1)
                ->and($exporter->observations()[0]->attributes()->toArray()['inference.tokens.total'] ?? null)->toBe(8);
        },
        instructorConfig: telemetryTestConfig([
            'telemetry' => [
                'enabled' => true,
                'driver' => 'otel',
                'drivers' => [
                    'otel' => [
                        'endpoint' => 'https://otel.example.invalid',
                    ],
                ],
                'projectors' => [
                    'instructor' => false,
                    'polyglot' => false,
                    'http' => false,
                    'agent_ctrl' => false,
                    'agents' => true,
                ],
            ],
        ]),
    );
});

it('keeps telemetry projector selection explicit', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $events = $app->service(CanHandleEvents::class);
            $exporter = $app->service(CanExportObservations::class);

            expect($exporter)->toBeInstanceOf(OtelExporter::class);

            $events->dispatch(new AgentExecutionStarted(
                agentId: 'agent-without-projector',
                executionId: 'exec-without-projector',
                parentAgentId: null,
                messageCount: 1,
                availableTools: 0,
            ));
            $events->dispatch(new AgentExecutionCompleted(
                agentId: 'agent-without-projector',
                executionId: 'exec-without-projector',
                parentAgentId: null,
                status: \Cognesy\Agents\Enums\ExecutionStatus::Completed,
                totalSteps: 1,
                totalUsage: InferenceUsage::none(),
                errors: null,
            ));

            expect($exporter->observations())->toBe([]);
        },
        instructorConfig: telemetryTestConfig([
            'telemetry' => [
                'enabled' => true,
                'driver' => 'otel',
                'drivers' => [
                    'otel' => [
                        'endpoint' => 'https://otel.example.invalid',
                    ],
                ],
                'projectors' => [
                    'instructor' => false,
                    'polyglot' => false,
                    'http' => false,
                    'agent_ctrl' => false,
                    'agents' => false,
                ],
            ],
        ]),
    );
});

it('flushes and shuts down telemetry across http, console, and messenger lifecycle events', function (): void {
    $exporter = new RecordingTelemetryExporter();

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($exporter): void {
            $dispatcher = $app->service('event_dispatcher');

            $dispatcher->dispatch(
                new TerminateEvent($app->kernel(), Request::create('/telemetry'), new Response('ok')),
                KernelEvents::TERMINATE,
            );

            $command = new Command('telemetry:test');
            $input = new ArrayInput([]);
            $output = new BufferedOutput();

            $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 0), ConsoleEvents::TERMINATE);
            $dispatcher->dispatch(new ConsoleErrorEvent($input, $output, new RuntimeException('boom'), $command), ConsoleEvents::ERROR);

            $worker = new Worker([], new TelemetryRecordingMessageBus());
            $envelope = new Envelope(new stdClass);

            $dispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'async'));
            $dispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('failed')));
            $dispatcher->dispatch(new WorkerStoppedEvent($worker));

            expect($exporter->flushCount)->toBe(6)
                ->and($exporter->shutdownCount)->toBe(4);
        },
        instructorConfig: telemetryTestConfig(),
        containerConfigurators: [
            SymfonyTelemetryServiceOverrides::exporter($exporter),
        ],
    );
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function telemetryTestConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
    ], $overrides);
}

function compiledSymfonyTelemetryContainer(array $config): ContainerBuilder
{
    $container = new ContainerBuilder;

    $bundle = new InstructorSymfonyBundle;
    $bundle->build($container);

    $extension = new InstructorSymfonyExtension;
    $extension->load([$config], $container);
    $container->compile();

    return $container;
}

final class TelemetryRecordingMessageBus implements MessageBusInterface
{
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return Envelope::wrap($message, $stamps);
    }
}
