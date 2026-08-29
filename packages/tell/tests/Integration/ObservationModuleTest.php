<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\StandardTellProfile;
use Cognesy\Tell\Composition\TellHostBuilder;
use Cognesy\Tell\Composition\TellModuleDefinition;
use Cognesy\Tell\Contracts\CanObserveTellExecution;
use Cognesy\Tell\Data\TellEventEnvelope;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Observability\PsrTellObserver;
use Psr\Log\AbstractLogger;

it('replaces observation pre-boot through the public normalized contract', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $received = new ArrayObject();
    $observer = new class($received) implements CanObserveTellExecution {
        public function __construct(private ArrayObject $received) {}

        public function observe(TellEventEnvelope $event): void {
            $this->received->append($event->toArray());
        }
    };
    $module = new TellModuleDefinition(
        id: 'observation.external',
        provides: [CanObserveTellExecution::class],
        factory: static fn (): object => $observer,
    );
    $host = TellHostBuilder::fromProfile(StandardTellProfile::runtime(
        $project,
        $paths,
        static fn () => FakeAgentDriver::fromResponses('safe answer'),
    ))->replace('observation.standard', $module)->boot();

    $host->runner()->run(TellRequest::prompt('secret prompt canary')->withDirectory($project));

    $events = $received->getArrayCopy();
    expect($events)->not->toBeEmpty()
        ->and(array_column($events, 'sequence'))->toBe(range(1, count($events)))
        ->and(array_values(array_filter($events, static fn (array $event): bool => $event['terminal'] !== null)))->toHaveCount(1)
        ->and(json_encode($events, JSON_THROW_ON_ERROR))->not->toContain('secret prompt canary')
        ->and(json_encode($events, JSON_THROW_ON_ERROR))->not->toContain('safe answer');
});

it('adapts normalized envelopes to PSR logging without exposing a source event', function (): void {
    $records = new ArrayObject();
    $logger = new class($records) extends AbstractLogger {
        public function __construct(private ArrayObject $records) {}

        public function log($level, Stringable|string $message, array $context = []): void {
            $this->records->append(compact('level', 'message', 'context'));
        }
    };
    $observer = new PsrTellObserver($logger);
    $observer->observe(new TellEventEnvelope(
        schema: 'tell.event.v1',
        kind: 'execution.completed',
        sequence: 1,
        executionId: 'exec-1',
        branch: 'main',
        session: null,
        occurredAt: new DateTimeImmutable('2026-08-26T12:00:00+00:00'),
        metadata: ['durationMs' => 7],
        terminal: 'completed',
        mode: TellExecutionMode::Stateless,
        agent: 'default',
    ));

    expect($records[0]['level'])->toBe('info')
        ->and($records[0]['message'])->toBe('execution.completed')
        ->and($records[0]['context'])->not->toHaveKey('source')
        ->and($records[0]['context']['schema'])->toBe('tell.event.v1');
});
