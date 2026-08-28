<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Diagnostics\StartupScanCounter;
use Cognesy\Tell\TellApplication;
use Symfony\Component\Console\Output\BufferedOutput;

it('keeps bare discovery within its semantic scan budget', function (): void {
    $scans = new StartupScanCounter;
    $application = new TellApplication(tellTestFactory(startupScans: $scans));
    $application->setAutoExit(false);

    $status = $application->runArgv(
        ['tell', '--output=json', '--dir='.tellTestProject()],
        new BufferedOutput,
    );

    expect($status)->toBe(0)
        ->and($scans->snapshot())->toBe([
            'workspaceDiscoveries' => 1,
            'agentDefinitionScans' => 1,
            'composerManifestScans' => 0,
        ]);
});

it('keeps agent listing within its semantic scan budget', function (): void {
    $scans = new StartupScanCounter;
    $application = new TellApplication(tellTestFactory(startupScans: $scans));
    $application->setAutoExit(false);

    $status = $application->runArgv(
        ['tell', 'agents', '--json', '--dir='.tellTestProject()],
        new BufferedOutput,
    );

    expect($status)->toBe(0)
        ->and($scans->snapshot())->toBe([
            'workspaceDiscoveries' => 0,
            'agentDefinitionScans' => 1,
            'composerManifestScans' => 0,
        ]);
});

it('reads the workspace once more when the invocation leaves the output format open', function (): void {
    $scans = new StartupScanCounter;
    $factory = tellTestFactory(
        decorate: static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('baseline answer')),
        startupScans: $scans,
    );
    $application = new TellApplication($factory);
    $application->setAutoExit(false);

    // Without --output the renderer cannot be chosen until branch configuration
    // has been read, which costs one discovery the explicit form does not pay.
    $status = $application->runArgv(
        ['tell', 'baseline prompt', '--dir='.tellTestProject()],
        new BufferedOutput,
    );

    expect($status)->toBe(0)
        ->and($scans->snapshot())->toBe([
            'workspaceDiscoveries' => 3,
            'agentDefinitionScans' => 2,
            'composerManifestScans' => 1,
        ]);
});

it('records the current automatic stateless turn scan baseline', function (): void {
    $scans = new StartupScanCounter;
    $factory = tellTestFactory(
        decorate: static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('baseline answer')),
        startupScans: $scans,
    );
    $application = new TellApplication($factory);
    $application->setAutoExit(false);

    $status = $application->runArgv(
        ['tell', 'baseline prompt', '--output=json', '--dir='.tellTestProject()],
        new BufferedOutput,
    );

    expect($status)->toBe(0)
        ->and($scans->snapshot())->toBe([
            'workspaceDiscoveries' => 2,
            'agentDefinitionScans' => 2,
            'composerManifestScans' => 1,
        ]);
});
