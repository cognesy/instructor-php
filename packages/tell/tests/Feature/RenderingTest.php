<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Render\EventsRenderer;
use Cognesy\Tell\Observability\TellEventNormalizer;
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
                static fn (string $line): string => json_decode($line, true, flags: JSON_THROW_ON_ERROR)['kind'],
                $lines,
            ))->toContain('execution.completed'),
        default => expect(trim($tester->getDisplay()))->toBe('rendered answer'),
    };
})->with(['toon', 'text', 'json', 'events']);

it('emits a monotonic, versioned, payload-free NDJSON sequence', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    $options = new TellOptions(prompt: 'events', directory: tellLastTemporaryRoot());
    $loop = $factory->build($options);
    $output = new BufferedOutput;
    (new EventsRenderer($output))->attach($loop);
    $loop->execute(AgentState::empty()->withUserMessage('events'));
    $rendered = array_map(
        static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", trim($output->fetch())))),
    );

    expect($rendered)->not->toBeEmpty()
        ->and(array_column($rendered, 'schema'))->each->toBe('tell.event.v1')
        ->and(array_column($rendered, 'sequence'))->toBe(range(1, count($rendered)))
        ->and(array_filter($rendered, static fn (array $event): bool => $event['terminal'] !== null))->toHaveCount(1)
        ->and(json_encode($rendered, JSON_THROW_ON_ERROR))->not->toContain('events');

    $unknown = (new TellEventNormalizer)->normalize(new \stdClass);
    expect($unknown['kind'])->toBe('unknown')
        ->and($unknown['metadata'])->toBe([]);
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
