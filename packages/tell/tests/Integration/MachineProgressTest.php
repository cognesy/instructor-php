<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Console\TellCommand;
use Symfony\Component\Console\Tester\CommandTester;

function tellProgressProject(): string {
    $project = tellLastTemporaryRoot() . '/machine-progress-project';
    mkdir($project, 0700, true);

    return $project;
}

function tellProgressTester(ScenarioStep ...$steps): CommandTester {
    return new CommandTester(new TellCommand(tellTestFactory(
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromSteps(...$steps)),
    )));
}

it('reports the parameters a tool was called with and the result it returned', function (): void {
    $tester = tellProgressTester(
        ScenarioStep::toolCall('shell', ['command' => 'echo traced', 'description' => 'prove it runs']),
        ScenarioStep::final('done'),
    );

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellProgressProject(), '--debug' => true, '--output' => 'json'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);

    $progress = $tester->getErrorOutput();

    // The channel composes with any output mode: stdout still carries JSON.
    expect(json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR))->toHaveKey('execution')
        ->and($progress)->toContain('[step.start] step=1')
        ->and($progress)->toContain('[tool.start] name=shell step=1 args={"command":"echo traced","description":"prove it runs"}')
        ->and($progress)->toContain('[tool.complete] name=shell status=ok')
        ->and($progress)->toContain('"text":"traced\n"')
        ->and($progress)->toContain('[execution.complete] status=completed');
});

it('calls a tool failed when the tool returned its own failure envelope', function (): void {
    $tester = tellProgressTester(
        ScenarioStep::toolCall('read', ['path' => 'missing.txt']),
        ScenarioStep::final('could not read'),
    );

    expect($tester->execute(
        ['prompt' => 'read a missing file', '--dir' => tellProgressProject(), '--debug' => true, '--output' => 'text'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);

    expect($tester->getErrorOutput())->toContain('[tool.complete] name=read status=failed')
        ->and($tester->getErrorOutput())->toContain('"code":"operation_failed"');
});

it('keeps an oversized payload parsable by emitting an excerpt and its real size', function (): void {
    $tester = tellProgressTester(
        ScenarioStep::toolCall('write', ['path' => 'big.txt', 'content' => str_repeat("a long line of content\n", 60)]),
        ScenarioStep::final('written'),
    );

    expect($tester->execute(
        ['prompt' => 'write', '--dir' => tellProgressProject(), '--debug' => true, '--output' => 'text'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);

    $line = array_values(array_filter(
        explode("\n", $tester->getErrorOutput()),
        static fn (string $candidate): bool => str_starts_with($candidate, '[tool.start] name=write'),
    ))[0] ?? '';

    expect($line)->toMatch('/ argsBytes=\d{4,}$/');

    // An excerpt stays valid JSON, so every payload value can be decoded.
    preg_match('/ args=(.*) argsBytes=/', $line, $matches);
    expect(json_decode($matches[1] ?? '', flags: JSON_THROW_ON_ERROR))->toBeString();
});

it('leaves the bare heartbeat alone when no progress channel was asked for', function (): void {
    $tester = tellProgressTester(
        ScenarioStep::toolCall('read', ['path' => 'notes.txt']),
        ScenarioStep::final('done'),
    );
    $project = tellProgressProject();
    file_put_contents($project . '/notes.txt', "a note\n");

    expect($tester->execute(
        ['prompt' => 'read the note', '--dir' => $project, '--output' => 'text'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);

    expect($tester->getErrorOutput())->not->toContain('[tool.start]')
        ->and($tester->getErrorOutput())->not->toContain('[step.start]');
});
