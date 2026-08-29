<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Console\TellCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

function tellTraceProject(): string {
    $project = tellLastTemporaryRoot() . '/step-trace-project';
    mkdir($project, 0700, true);

    return $project;
}

function tellTraceTester(ScenarioStep ...$steps): CommandTester {
    return new CommandTester(new TellCommand(tellTestFactory(
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromSteps(...$steps)),
    )));
}

it('traces steps and tool calls on stderr while stdout keeps the requested format', function (): void {
    $tester = tellTraceTester(
        ScenarioStep::toolCall('read', ['path' => 'notes.txt']),
        ScenarioStep::final('done reading'),
    );
    $project = tellTraceProject();
    file_put_contents($project . '/notes.txt', "first line\nsecond line\n");

    expect($tester->execute(
        ['prompt' => 'read the note', '--dir' => $project, '--output' => 'text'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);

    $trace = $tester->getErrorOutput();

    // The trace is a separate channel: the requested format still owns stdout.
    expect(trim($tester->getDisplay()))->toBe('done reading')
        ->and($trace)->toContain('● step 1')
        ->and($trace)->toContain('▸ read `notes.txt`')
        ->and($trace)->toContain('✔ read')
        ->and($trace)->toContain('│')
        ->and($trace)->toContain('first line')
        ->and($trace)->toContain('● completed');
});

it('shows the argument that matters for each tool rather than its raw JSON', function (): void {
    $tester = tellTraceTester(
        ScenarioStep::toolCall('shell', ['command' => 'echo traced', 'description' => 'prove it runs']),
        ScenarioStep::toolCall('write', ['path' => 'out.txt', 'content' => "alpha\nbeta\n"]),
        ScenarioStep::final('done'),
    );

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellTraceProject(), '--output' => 'text'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);

    $trace = $tester->getErrorOutput();

    expect($trace)->toContain('▸ shell [prove it runs]')
        ->and($trace)->toContain('│ echo traced')
        ->and($trace)->toContain('▸ write `out.txt` (11 bytes)')
        ->and($trace)->toContain('│ alpha')
        ->and($trace)->not->toContain('{"command"');
});

it('reports a failed tool call with the reason the tool gave', function (): void {
    $tester = tellTraceTester(
        ScenarioStep::toolCall('read', ['path' => 'missing.txt']),
        ScenarioStep::final('could not read'),
    );

    expect($tester->execute(
        ['prompt' => 'read a missing file', '--dir' => tellTraceProject(), '--output' => 'text'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);

    $trace = $tester->getErrorOutput();

    // The envelope reason and the result text are the same sentence for most
    // failing tools, and the trace says it once.
    expect($trace)->toContain('✘ read')
        ->and($trace)->toContain('operation_failed')
        ->and(substr_count($trace, 'Cannot read'))->toBe(1);
});

it('stays silent unless a trace was asked for', function (): void {
    $tester = tellTraceTester(
        ScenarioStep::toolCall('read', ['path' => 'notes.txt']),
        ScenarioStep::final('done reading'),
    );
    $project = tellTraceProject();
    file_put_contents($project . '/notes.txt', "first line\n");

    expect($tester->execute(
        ['prompt' => 'read the note', '--dir' => $project, '--output' => 'text'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);

    expect($tester->getErrorOutput())->not->toContain('step 1')
        ->and($tester->getErrorOutput())->not->toContain('first line');
});

it('previews a long body, states what it elided, and stops abridging at -vvv', function (): void {
    $lines = implode("\n", array_map(static fn (int $i): string => "line {$i}", range(1, 30)));
    $preview = tellTraceTester(
        ScenarioStep::toolCall('read', ['path' => 'long.txt']),
        ScenarioStep::final('read it'),
    );
    $project = tellTraceProject();
    file_put_contents($project . '/long.txt', $lines . "\n");
    expect($preview->execute(
        ['prompt' => 'read', '--dir' => $project, '--output' => 'text'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);
    expect($preview->getErrorOutput())->toContain('line 1')
        ->and($preview->getErrorOutput())->not->toContain('line 30')
        ->and($preview->getErrorOutput())->toMatch('/⋯ \d+ more lines/');

    $full = tellTraceTester(
        ScenarioStep::toolCall('read', ['path' => 'long.txt']),
        ScenarioStep::final('read it'),
    );
    expect($full->execute(
        ['prompt' => 'read', '--dir' => $project, '--output' => 'text'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_DEBUG],
    ))->toBe(0);
    expect($full->getErrorOutput())->toContain('line 30')
        ->and($full->getErrorOutput())->not->toContain('more lines');
});

it('decorates the trace only when stderr is a terminal', function (): void {
    $plain = tellTraceTester(ScenarioStep::final('done'));
    expect($plain->execute(
        ['prompt' => 'work', '--dir' => tellTraceProject(), '--output' => 'text'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);
    expect($plain->getErrorOutput())->toContain('● step 1')
        ->and($plain->getErrorOutput())->not->toContain("\033[");

    $decorated = tellTraceTester(ScenarioStep::final('done'));
    expect($decorated->execute(
        ['prompt' => 'work', '--dir' => tellTraceProject(), '--output' => 'text'],
        ['capture_stderr_separately' => true, 'decorated' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);
    expect($decorated->getErrorOutput())->toContain("\033[");
});

it('refuses machine progress that was also asked to be quiet', function (): void {
    $tester = tellTraceTester(ScenarioStep::final('done'));

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellTraceProject(), '--debug' => true],
        ['verbosity' => OutputInterface::VERBOSITY_QUIET],
    ))->toBe(2)
        ->and($tester->getDisplay())->toContain('--debug and --quiet cannot be used together');
});

it('separates the trace from the answer with a blank line, and adds none when nothing traced', function (): void {
    $traced = tellTraceTester(ScenarioStep::final('done'));

    expect($traced->execute(
        ['prompt' => 'work', '--dir' => tellTraceProject(), '--output' => 'json'],
        ['capture_stderr_separately' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);

    // json puts nothing of its own on stderr, so the channel's own tail is
    // exactly one blank line after the closing band.
    expect($traced->getErrorOutput())->toEndWith("\n\n")
        ->and($traced->getErrorOutput())->not->toEndWith("\n\n\n");

    $silent = tellTraceTester(ScenarioStep::final('done'));

    expect($silent->execute(
        ['prompt' => 'work', '--dir' => tellTraceProject(), '--output' => 'json'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);
    expect($silent->getErrorOutput())->toBe('');
});
