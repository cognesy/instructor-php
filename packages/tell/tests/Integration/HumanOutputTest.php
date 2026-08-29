<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Command\ConfigCommand;
use Cognesy\Tell\Console\TellCommand;
use Cognesy\Tell\Console\TellOptions;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Symfony\Component\Console\Tester\CommandTester;

const TELL_HUMAN_ANSWER = <<<'MD'
# Findings

The `discover()` walk stops at the **schema record**, not the marker:

- one workspace per project
- nothing above it

> Anything carrying a schema is validated strictly.
MD;

function tellHumanProject(): string {
    $project = tellLastTemporaryRoot() . '/human-output-project';
    mkdir($project, 0700, true);

    return $project;
}

function tellHumanTester(): CommandTester {
    return new CommandTester(new TellCommand(tellTestFactory(
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
            new RecordingDriver(new RequestRecorder(), TELL_HUMAN_ANSWER),
        ),
    )));
}

it('renders the answer as markdown when stdout is a terminal', function (): void {
    $tester = tellHumanTester();

    expect($tester->execute(
        ['prompt' => 'explain', '--dir' => tellHumanProject(), '--output' => 'human'],
        ['decorated' => true],
    ))->toBe(0);

    $display = $tester->getDisplay(true);

    // Headings, bullets, inline code and bold are decorated rather than printed
    // as their Markdown source.
    expect($display)->toContain("\033[")
        ->and($display)->toContain('•')
        ->and($display)->not->toContain('- one workspace per project')
        ->and($display)->not->toContain('`discover()`');
});

it('leaves the answer as plain markdown when stdout is not a terminal', function (): void {
    $tester = tellHumanTester();

    expect($tester->execute(
        ['prompt' => 'explain', '--dir' => tellHumanProject(), '--output' => 'human'],
        ['decorated' => false],
    ))->toBe(0);

    $display = $tester->getDisplay(true);

    expect($display)->toContain(TELL_HUMAN_ANSWER)
        ->and($display)->not->toContain("\033[");
});

it('does not let console markup in an answer reach the formatter', function (): void {
    $tester = new CommandTester(new TellCommand(tellTestFactory(
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
            new RecordingDriver(new RequestRecorder(), 'Use <error> and <T> as generic parameters.'),
        ),
    )));

    expect($tester->execute(
        ['prompt' => 'explain', '--dir' => tellHumanProject(), '--output' => 'human'],
        ['decorated' => false],
    ))->toBe(0);

    expect($tester->getDisplay(true))->toContain('Use <error> and <T> as generic parameters.');
});

it('accepts human alongside the other output modes and rejects unknown ones', function (): void {
    tellTestFactory();
    $project = tellHumanProject();

    expect((new TellOptions(prompt: 'p', directory: $project, output: 'human'))->output)->toBe('human');

    expect(static fn () => new TellOptions(prompt: 'p', directory: $project, output: 'markdown'))
        ->toThrow(InvalidArgumentException::class, '--output must be one of: toon, text, human, json, events.');
});

it('uses the branch-configured output format when the invocation does not choose one', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver(new RequestRecorder(), TELL_HUMAN_ANSWER),
    ));
    $project = tellHumanProject();
    $factory->workspace()->initialize($project);

    $config = new CommandTester(new ConfigCommand($factory));
    expect($config->execute([
        'action' => 'set',
        'key' => 'output',
        'value' => '"human"',
        '--dir' => $project,
        '--if-version' => '0',
        '--json' => true,
    ]))->toBe(0);

    $tester = new CommandTester(new TellCommand($factory));
    expect($tester->execute(
        ['prompt' => 'explain', '--dir' => $project],
        ['decorated' => true],
    ))->toBe(0);

    // Rendered markdown, not the TOON projection the bundled default produces.
    expect($tester->getDisplay(true))->toContain("\033[")
        ->and($tester->getDisplay(true))->not->toContain('execution:');
});

it('lets an explicit --output win over the branch-configured format', function (): void {
    $factory = tellTestFactory(static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
        new RecordingDriver(new RequestRecorder(), 'plain answer'),
    ));
    $project = tellHumanProject();
    $factory->workspace()->initialize($project);

    $config = new CommandTester(new ConfigCommand($factory));
    $config->execute([
        'action' => 'set', 'key' => 'output', 'value' => '"human"',
        '--dir' => $project, '--if-version' => '0', '--json' => true,
    ]);

    $tester = new CommandTester(new TellCommand($factory));
    expect($tester->execute(
        ['prompt' => 'explain', '--dir' => $project, '--output' => 'json'],
        ['decorated' => true],
    ))->toBe(0);

    $payload = json_decode($tester->getDisplay(true), true, flags: JSON_THROW_ON_ERROR);
    expect($payload['answer'])->toBe('plain answer');
});

it('rejects an unsupported output format at the config boundary', function (): void {
    $factory = tellTestFactory();
    $project = tellHumanProject();
    $factory->workspace()->initialize($project);

    $config = new CommandTester(new ConfigCommand($factory));
    $config->execute([
        'action' => 'set', 'key' => 'output', 'value' => '"markdown"',
        '--dir' => $project, '--if-version' => '0', '--json' => true,
    ]);

    expect($config->getDisplay())->toContain('must be one of: toon, text, human, json, events');
});

it('reports output among the effective branch settings', function (): void {
    $factory = tellTestFactory();
    $project = tellHumanProject();
    $factory->workspace()->initialize($project);

    $config = new CommandTester(new ConfigCommand($factory));
    expect($config->execute([
        'action' => 'effective', '--dir' => $project, '--json' => true,
    ]))->toBe(0);

    $payload = json_decode($config->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['values']['output'])->toBe('human')
        ->and($payload['provenance']['output'])->toBe('bundled');
});
