<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Adapter\Console\Symfony\TellCommand;
use Cognesy\Tell\Core\Agent\TellAgentFactory;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * execution.mode and publication.status report what the turn actually did. A
 * stateless turn writes no conversation state anywhere, so requested mode must
 * not be mistaken for achieved publication.
 */
it('reports a stateless mode for a turn run outside any workspace', function (): void {
    $factory = tellExecutionModeFactory();
    $project = tellExecutionModeProject();
    $tester = new CommandTester(tellTestCommand($factory));

    expect($tester->execute([
        'prompt' => 'no workspace here',
        '--dir' => $project,
        '--output' => 'json',
    ]))->toBe(0);

    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['execution']['requestedMode'])->toBe('automatic')
        ->and($payload['execution']['mode'])->toBe('stateless')
        ->and($payload['execution']['status'])->toBe('completed')
        ->and($payload['publication']['requested'])->toBeFalse()
        ->and($payload['publication']['status'])->toBe('not_applicable')
        ->and($payload['publication']['headChanged'])->toBeFalse()
        ->and(tellTestWorkspaces()->discover($project))->toBeNull();
});

it('reports a durable mode only when the turn published an arena turn', function (): void {
    $factory = tellExecutionModeFactory();
    $project = tellExecutionModeProject();
    tellTestWorkspaces()->initialize($project);
    $workspace = tellTestWorkspaces()->discover($project);
    $tester = new CommandTester(tellTestCommand($factory));

    expect($tester->execute([
        'prompt' => 'workspace turn',
        '--dir' => $project,
        '--output' => 'json',
    ]))->toBe(0);

    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['execution']['requestedMode'])->toBe('automatic')
        ->and($payload['execution']['mode'])->toBe('durable')
        ->and($payload['execution']['status'])->toBe('completed')
        ->and($payload['publication']['requested'])->toBeTrue()
        ->and($payload['publication']['status'])->toBe('published')
        ->and($payload['publication']['headChanged'])->toBeTrue()
        ->and((new FilesystemArena($workspace))->readRef('main')->head)->not->toBeNull();
});

it('reports a transient mode for an explicitly transient turn outside any workspace', function (): void {
    $factory = tellExecutionModeFactory();
    $project = tellExecutionModeProject();
    $tester = new CommandTester(tellTestCommand($factory));

    expect($tester->execute([
        'prompt' => 'no workspace here',
        '--dir' => $project,
        '--transient' => true,
        '--output' => 'json',
    ]))->toBe(0);

    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['execution']['requestedMode'])->toBe('transient')
        ->and($payload['execution']['mode'])->toBe('transient')
        ->and($payload['execution']['status'])->toBe('completed')
        ->and($payload['publication']['requested'])->toBeFalse()
        ->and($payload['publication']['status'])->toBe('not_applicable')
        ->and($payload['publication']['headChanged'])->toBeFalse();
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
