<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\Standalone\StandaloneTellBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

it('assembles the complete CLI surface from the standalone builder', function (): void {
    $project = tellTestProject();
    $application = StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('unused'))
        ->buildCli();
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
            'runs',
            'sessions',
            'tools',
            'tool',
            'history',
            'transcript',
        )
        ->and(json_decode($rendered, true, 512, JSON_THROW_ON_ERROR)['agents'])->toBeArray();
});

it('rejects duplicate custom command names while resolving the CLI root', function (): void {
    $project = tellTestProject();

    StandaloneTellBuilder::in($project, standardHostPaths($project))
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('unused'))
        ->withCommand(new Command('tell'))
        ->buildCli();
})->throws(InvalidArgumentException::class, 'Duplicate Tell command name: tell.');
