<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\TellCommand;
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

function tellHumanProject(): string
{
    $project = tellLastTemporaryRoot().'/human-output-project';
    mkdir($project, 0700, true);

    return $project;
}

function tellHumanTester(): CommandTester
{
    return new CommandTester(new TellCommand(tellTestFactory(
        static fn (AgentLoop $loop): AgentLoop => $loop->withDriver(
            new RecordingDriver(new RequestRecorder, TELL_HUMAN_ANSWER),
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
            new RecordingDriver(new RequestRecorder, 'Use <error> and <T> as generic parameters.'),
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
