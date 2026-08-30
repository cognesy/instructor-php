<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\Standalone\StandardTellProfile;
use Cognesy\Tell\Composition\Standalone\TellHost;
use Cognesy\Tell\Composition\Standalone\TellHostBuilder;
use Cognesy\Tell\Tests\Support\TellHostConformance;

it('keeps the standalone host conformant across repeated isolated executions', function (): void {
    $project = tellTestProject();
    $paths = standardHostPaths($project);
    $contract = new TellHostConformance(
        static fn (string $response): TellHost => TellHostBuilder::fromProfile(
            StandardTellProfile::runtime(
                directory: $project,
                paths: $paths,
                driverFactory: static fn () => FakeAgentDriver::fromResponses($response),
            ),
        )->boot(),
    );

    expect(fn () => $contract->verify($project))->not->toThrow(Throwable::class);
});
