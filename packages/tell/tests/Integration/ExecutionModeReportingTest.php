<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Console\TellCommand;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * execution.mode and execution.durable report what the turn actually persisted.
 * A stateless turn writes no conversation state anywhere, so it must not claim
 * durability just because it is not transient.
 */
it('reports a stateless mode for a turn run outside any workspace', function (): void {
    $factory = tellExecutionModeFactory();
    $project = tellExecutionModeProject();
    $tester = new CommandTester(new TellCommand($factory));

    expect($tester->execute([
        'prompt' => 'no workspace here',
        '--dir' => $project,
        '--output' => 'json',
    ]))->toBe(0);

    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['execution'])->toBe(['mode' => 'stateless', 'durable' => false])
        ->and($factory->workspace()->discover($project))->toBeNull();
});

it('reports a durable mode only when the turn published an arena turn', function (): void {
    $factory = tellExecutionModeFactory();
    $project = tellExecutionModeProject();
    $factory->workspace()->initialize($project);
    $workspace = $factory->workspace()->discover($project);
    $tester = new CommandTester(new TellCommand($factory));

    expect($tester->execute([
        'prompt' => 'workspace turn',
        '--dir' => $project,
        '--output' => 'json',
    ]))->toBe(0);

    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['execution'])->toBe(['mode' => 'durable', 'durable' => true])
        ->and((new FilesystemArena($workspace))->readRef('main')->head)->not->toBeNull();
});

it('reports a transient mode for an explicitly transient turn outside any workspace', function (): void {
    $factory = tellExecutionModeFactory();
    $project = tellExecutionModeProject();
    $tester = new CommandTester(new TellCommand($factory));

    expect($tester->execute([
        'prompt' => 'no workspace here',
        '--dir' => $project,
        '--transient' => true,
        '--output' => 'json',
    ]))->toBe(0);

    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['execution'])->toBe(['mode' => 'transient', 'durable' => false]);
});

function tellExecutionModeFactory(): TellAgentFactory {
    return tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver(new RequestRecorder(), 'answer'),
    ));
}

function tellExecutionModeProject(): string {
    $project = tellLastTemporaryRoot() . '/execution-mode-project';
    mkdir($project, 0700, true);

    return $project;
}
