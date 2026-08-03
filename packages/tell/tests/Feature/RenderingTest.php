<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Render\EventsRenderer;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\TellApplication;
use Cognesy\Tell\TellCommand;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

it('selects text, json, and event renderers deterministically', function (string $mode): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('rendered answer')));
    $tester = new CommandTester(new TellCommand($factory));
    $status = $tester->execute(
        ['prompt' => 'hello', '--output' => $mode],
        ['capture_stderr_separately' => true],
    );
    $lines = array_values(array_filter(explode("\n", trim($tester->getDisplay()))));

    expect($status)->toBe(0);
    match ($mode) {
        'toon' => expect(Toon::decode($tester->getDisplay())['answer'])->toBe('rendered answer'),
        'json' => expect($lines)->toHaveCount(1)
            ->and(json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR)['answer'])->toBe('rendered answer'),
        'events' => expect($lines === [])->toBeFalse()
            ->and(array_map(
                static fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['event'],
                $lines,
            ))->toContain('AgentExecutionCompleted'),
        default => expect(trim($tester->getDisplay()))->toBe('rendered answer'),
    };
})->with(['toon', 'text', 'json', 'events']);

it('emits exactly the independently observed event sequence as ndjson', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    $options = new TellOptions(prompt: 'events', directory: tellLastTemporaryRoot());
    $loop = $factory->build($options);
    $output = new BufferedOutput;
    (new EventsRenderer($output))->attach($loop);
    $observed = [];
    $loop->wiretap(static function (object $event) use (&$observed): void {
        $observed[] = (new ReflectionClass($event))->getShortName();
    });

    $loop->execute(AgentState::empty()->withUserMessage('events'));
    $rendered = array_map(
        static fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['event'],
        array_values(array_filter(explode("\n", trim($output->fetch())))),
    );

    expect($rendered)->toBe($observed);
    expect($rendered === [])->toBeFalse();
});

it('keeps quiet output final-only and makes verbose tool progress explicit', function (): void {
    $driver = new FakeAgentDriver([
        ScenarioStep::toolCall('list_agents'),
        ScenarioStep::final('tool answer'),
    ]);
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver($driver));

    $quietApplication = new TellApplication($factory);
    $quietApplication->setAutoExit(false);
    $quiet = new ApplicationTester($quietApplication);
    $quiet->run(
        ['command' => 'tell', 'prompt' => 'use a tool', '--quiet' => true],
        ['capture_stderr_separately' => true],
    );
    expect(Toon::decode($quiet->getDisplay())['answer'])->toBe('tool answer')
        ->and($quiet->getErrorOutput())->toBe('');

    $verboseApplication = new TellApplication($factory);
    $verboseApplication->setAutoExit(false);
    $verbose = new ApplicationTester($verboseApplication);
    $verbose->run(
        ['command' => 'tell', 'prompt' => 'use a tool', '--verbose' => true],
        ['capture_stderr_separately' => true],
    );
    expect(Toon::decode($verbose->getDisplay())['answer'])->toBe('tool answer')
        ->and($verbose->getErrorOutput())->toContain('[tool.start] name=list_agents')
        ->toContain('[tool.complete] name=list_agents status=ok');
});
