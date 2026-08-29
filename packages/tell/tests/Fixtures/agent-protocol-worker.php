<?php

declare(strict_types=1);

$packageRoot = dirname(__DIR__, 2);
$monorepoAutoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
$packageAutoload = $packageRoot . '/vendor/autoload.php';
require file_exists($monorepoAutoload) ? $monorepoAutoload : $packageAutoload;

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Composition\TellHost;
use Cognesy\Tell\Configuration\TellPaths;
use Cognesy\Tell\Console\TellApplication;
use Cognesy\Tell\Runtime\TellAgentFactory;

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

$factory = new TellAgentFactory(
    paths: new TellPaths(
        packageAgents: $packageRoot . '/resources/agents',
        home: $project . '/.tell-rpc-testing',
    ),
    driver: $driver,
    composerVendorDir: is_string($composerVendorDir) && $composerVendorDir !== '' ? $composerVendorDir : null,
);

$host = TellHost::standard(
    directory: $project,
    paths: $factory->paths(),
    agentFactory: $factory,
    cancellation: $cancellation,
)->boot();
$application = TellApplication::fromHost($host);
$application->setAutoExit(false);
try {
    $exitCode = $application->runArgv();
} finally {
    $host->dispose();
}
exit($exitCode);
