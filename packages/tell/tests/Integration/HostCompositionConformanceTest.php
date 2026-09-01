<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\Standalone\Profile\StandardTellProfile;
use Cognesy\Tell\Composition\Standalone\Host\TellHostBuilder;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Tests\Support\TellHostConformance;
use Cognesy\Tell\Tests\Support\TellHostConformanceHarness;

it('keeps the standalone host conformant across repeated isolated executions', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $contract = new TellHostConformance(
        static function (string $response) use ($project, $paths): TellHostConformanceHarness {
            $host = TellHostBuilder::fromProfile(StandardTellProfile::runtime(
                directory: $project,
                paths: $paths,
                driverFactory: static fn () => FakeAgentDriver::fromResponses($response),
            ))->boot();

            return new TellHostConformanceHarness(
                runner: static fn (): CanRunTell => $host->runner(),
                dispose: static function () use ($host): void {
                    $host->dispose();
                },
            );
        },
    );

    expect(fn () => $contract->verify($project))->not->toThrow(Throwable::class);
});

it('keeps the shared host conformance law independent of standalone host types', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/Support/TellHostConformance.php')
        . (string) file_get_contents(dirname(__DIR__) . '/Support/TellHostConformanceHarness.php');

    expect($source)->not->toContain('Composition\\Standalone\\Host')
        ->not->toContain('TellHostDisposedException')
        ->not->toContain('TellHostBuilder');
});
