<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__, 2);
$monorepoAutoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
$packageAutoload = $packageRoot . '/vendor/autoload.php';
require file_exists($monorepoAutoload) ? $monorepoAutoload : $packageAutoload;

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost;
use Cognesy\Tell\Core\Paths\TellPaths;
use Cognesy\Tell\Adapter\Console\Symfony\TellConsoleApplication;
use Cognesy\Tell\Data\TellCommandDescriptors;
use Cognesy\Tell\Core\Agent\TellAgentFactory;
use Cognesy\Tell\Capability\Execution\System\SystemTellClock;
use Cognesy\Tell\Capability\Model\Polyglot\PolyglotTellModelResolver;
use Cognesy\Tell\Capability\Secrets\Standard\StandardTellSecretResolver;
use Cognesy\Tell\Capability\Agent\ComposerDiscovery\ComposerTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Definitions\FilesystemTellAgentDefinitions;
use Cognesy\Tell\Capability\Agent\Standard\StandardTellAgentContribution;
use Cognesy\Tell\Capability\Agent\Subagent\TellSubagentContribution;
use Cognesy\Tell\Capability\Tool\AskUser\AskUserToolContribution;
use Cognesy\Tell\Capability\Tool\Coding\CodingToolContribution;

$scenario = getenv('TELL_RPC_SCENARIO') ?: 'success';
$project = getenv('TELL_RPC_PROJECT') ?: sys_get_temp_dir();
$composerVendorDir = getenv('TELL_RPC_COMPOSER_VENDOR_DIR');
$cancellation = new InMemoryCancellationSource();

$driver = match ($scenario) {
    'success' => FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('read_file', ['path' => 'protocol-evidence.txt']),
        ScenarioStep::final('safe protocol result'),
    ),
    'failure' => FakeAgentDriver::fromSteps(
        ScenarioStep::error('provider-secret-canary'),
    ),
    'large' => FakeAgentDriver::fromSteps(
        ScenarioStep::final(str_repeat('x', 300_000)),
    ),
    'cancelled' => FakeAgentDriver::fromSteps(
        ScenarioStep::final('this response must not be reached'),
    ),
    default => throw new RuntimeException('Unsupported fixture scenario.'),
};

if ($scenario === 'cancelled') {
    $cancellation->cancel('cancellation-secret-canary');
}

$paths = new TellPaths(
    packageAgents: $packageRoot . '/resources/agents',
    home: $project . '/.tell-rpc-testing',
);
$factory = new TellAgentFactory(
    paths: $paths,
    tracer: new \Cognesy\Tell\Capability\Observation\FilesystemTrace\StandardTellExecutionTracer($paths),
    clock: new SystemTellClock(),
    modelResolver: new PolyglotTellModelResolver($paths, new StandardTellSecretResolver($paths, $project)),
    definitionLoader: new FilesystemTellAgentDefinitions($paths),
    contributions: [
        new ComposerTellAgentContribution(
            vendorDirectory: is_string($composerVendorDir) && $composerVendorDir !== '' ? $composerVendorDir : null,
        ),
        new CodingToolContribution($paths),
        new AskUserToolContribution(),
        new TellSubagentContribution(),
        new StandardTellAgentContribution(),
    ],
    driver: $driver,
);

$host = StandaloneTellHost::cliBuilder(
    directory: $project,
    paths: $factory->paths(),
    agentBuilder: $factory,
    cancellation: $cancellation,
)->boot();
$application = new TellConsoleApplication(TellCommandDescriptors::merge(
    ...array_map(static fn ($contributor) => $contributor->commands(), $host->commandContributors()),
));
$application->setAutoExit(false);
try {
    $exitCode = $application->runArgv();
} finally {
    $host->dispose();
}
exit($exitCode);
