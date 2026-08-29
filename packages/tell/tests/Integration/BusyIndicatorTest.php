<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\TellCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

function tellBusyProject(): string
{
    $project = tellLastTemporaryRoot().'/busy-indicator-project';
    mkdir($project, 0700, true);

    return $project;
}

function tellBusyTester(ScenarioStep ...$steps): CommandTester
{
    return new CommandTester(new TellCommand(tellTestFactory(
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(FakeAgentDriver::fromSteps(...$steps)),
    )));
}

it('says what a human-output turn is doing when no progress channel was asked for', function (): void {
    $tester = tellBusyTester(
        ScenarioStep::toolCall('shell', ['command' => 'echo busy', 'description' => 'check the suite']),
        ScenarioStep::final('done'),
    );

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellBusyProject(), '--output' => 'human'],
        ['capture_stderr_separately' => true, 'decorated' => true],
    ))->toBe(0);

    $busy = $tester->getErrorOutput();

    expect($busy)->toContain('step 1')
        ->and($busy)->toContain('thinking')
        ->and($busy)->toContain('shell: check the suite')
        // One line, redrawn in place: it carries carriage returns and no newline.
        ->and($busy)->not->toContain("\n")
        // The busy line replaces the bare heartbeat rather than joining it.
        ->and($busy)->not->toContain('[inference.start]')
        ->and(trim($tester->getDisplay()))->toBe('done');
});

it('leaves the terminal clean so the answer starts in empty space', function (): void {
    $tester = tellBusyTester(ScenarioStep::final('done'));

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellBusyProject(), '--output' => 'human'],
        ['capture_stderr_separately' => true, 'decorated' => true],
    ))->toBe(0);

    expect($tester->getErrorOutput())->toEndWith("\r\033[K");
});

it('writes no busy line into a redirected stream', function (): void {
    $tester = tellBusyTester(ScenarioStep::final('done'));

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellBusyProject(), '--output' => 'human'],
        ['capture_stderr_separately' => true],
    ))->toBe(0);

    // A line that erases itself is noise once it is a file, so a stream that
    // is not a terminal keeps whatever it carried before and nothing more.
    expect($tester->getErrorOutput())->not->toContain("\r")
        ->and($tester->getErrorOutput())->not->toContain('⠙');
});

it('stands down for every channel that supersedes it', function (): void {
    foreach ([
        ['--output' => 'human', '--debug' => true],
        ['--output' => 'toon'],
    ] as $invocation) {
        $tester = tellBusyTester(ScenarioStep::final('done'));

        expect($tester->execute(
            ['prompt' => 'work', '--dir' => tellBusyProject(), ...$invocation],
            ['capture_stderr_separately' => true, 'decorated' => true],
        ))->toBe(0);
        expect($tester->getErrorOutput())->not->toContain('⠙');
    }

    $verbose = tellBusyTester(ScenarioStep::final('done'));
    expect($verbose->execute(
        ['prompt' => 'work', '--dir' => tellBusyProject(), '--output' => 'human'],
        ['capture_stderr_separately' => true, 'decorated' => true, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE],
    ))->toBe(0);
    expect($verbose->getErrorOutput())->toContain('step 1')
        ->and($verbose->getErrorOutput())->not->toContain('⠙');
});

it('gets out of the way while a tool is asking the person a question', function (): void {
    $tester = tellBusyTester(
        ScenarioStep::toolCall('ask_user', ['question' => 'which branch?']),
        ScenarioStep::final('done'),
    );

    expect($tester->execute(
        ['prompt' => 'work', '--dir' => tellBusyProject(), '--output' => 'human', '--answer' => ['main']],
        ['capture_stderr_separately' => true, 'decorated' => true],
    ))->toBe(0);

    $busy = $tester->getErrorOutput();

    // The question is never drawn over: the line is erased before the tool runs
    // and the indicator only comes back once the answer is in.
    expect($busy)->not->toContain('ask: which branch?')
        ->and($busy)->toContain('working')
        ->and($busy)->toEndWith("\r\033[K");
});
