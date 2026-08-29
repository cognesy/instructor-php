<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\TellHost;
use Cognesy\Tell\Composition\TellHostGraphException;
use Cognesy\Tell\Composition\TellModuleDefinition;
use Cognesy\Tell\Console\TellApplication;
use Cognesy\Tell\Contracts\CanContributeTellCommands;
use Cognesy\Tell\Data\TellCommandDescriptor;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Symfony\Component\Console\Output\BufferedOutput;

it('assembles the complete compatible CLI surface from the standard host', function (): void {
    $project = tellTestProject();
    $host = TellHost::standard(
        $project,
        standardHostPaths($project),
        static fn () => FakeAgentDriver::fromResponses('unused'),
    )->boot();
    $application = TellApplication::fromHost($host);
    $application->setAutoExit(false);
    $output = new BufferedOutput();

    $exit = $application->runArgv(['tell', 'agents', '--json', '--dir', $project], $output);
    $rendered = $output->fetch();

    expect($exit)->toBe(0, $rendered)
        ->and(array_keys($application->all()))->toContain(
            'tell',
            'agent',
            'agents',
            'auth',
            'branch',
            'clear',
            'checkout',
            'compact',
            'config',
            'context',
            'describe',
            'init',
            'models',
            'providers',
            'reset',
            'planes',
            'sessions',
            'tools',
            'tool',
            'history',
            'transcript',
        )
        ->and(json_decode($rendered, true, 512, JSON_THROW_ON_ERROR)['agents'])->toBeArray();

    $host->dispose();
});

it('rejects duplicate contributed command names before returning a booted host', function (): void {
    $project = tellTestProject();
    $duplicate = new TellModuleDefinition(
        id: 'commands.duplicate-test',
        provides: [CanContributeTellCommands::class],
        factory: static fn (): object => new class implements CanContributeTellCommands {
            public function commands(): TellCommandDescriptors {
                return new TellCommandDescriptors(new TellCommandDescriptor('tell', static fn (): object => new stdClass()));
            }
        },
    );

    expect(fn () => TellHost::standard(
        $project,
        standardHostPaths($project),
        static fn () => FakeAgentDriver::fromResponses('unused'),
    )->with($duplicate)->boot())->toThrow(TellHostGraphException::class, 'duplicate command name tell');
});
