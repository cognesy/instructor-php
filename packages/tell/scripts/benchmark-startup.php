#!/usr/bin/env php
<?php

declare(strict_types=1);

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Diagnostics\StartupScanCounter;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellPaths;
use Cognesy\Tell\TellApplication;
use Symfony\Component\Console\Output\BufferedOutput;

$findAutoload = static function (): string {
    $monorepoAutoload = dirname(__DIR__, 3).'/vendor/autoload.php';
    if (is_file($monorepoAutoload)) {
        return $monorepoAutoload;
    }
    $directory = __DIR__;
    do {
        $autoload = $directory.'/vendor/autoload.php';
        if (is_file($autoload)) {
            return $autoload;
        }
        $parent = dirname($directory);
        if ($parent === $directory) {
            break;
        }
        $directory = $parent;
    } while (true);

    throw new RuntimeException('Unable to locate vendor/autoload.php. Run composer install first.');
};

require $findAutoload();

$iterations = 10;
$enforce = false;
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
    if ($argument === '--enforce') {
        $enforce = true;
        continue;
    }
    if (preg_match('/^--iterations=(\d+)$/', $argument, $matches) === 1) {
        $iterations = (int) $matches[1];
        continue;
    }
    fwrite(STDERR, "Usage: benchmark-startup.php [--iterations=1..100] [--enforce]\n");
    exit(2);
}
if ($iterations < 1 || $iterations > 100) {
    fwrite(STDERR, "--iterations must be between 1 and 100.\n");
    exit(2);
}

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tell-startup-'.bin2hex(random_bytes(8));
$project = $root.DIRECTORY_SEPARATOR.'project';
$home = $root.DIRECTORY_SEPARATOR.'home';
if (! mkdir($project, 0700, true) || ! mkdir($home, 0700, true)) {
    throw new RuntimeException("Unable to create benchmark workspace: {$root}");
}

$removeDirectory = static function (string $directory) use ($root): void {
    if ($directory !== $root || ! is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
};

$percentile = static function (array $samples, float $fraction): float {
    sort($samples, SORT_NUMERIC);
    $index = max(0, (int) ceil(count($samples) * $fraction) - 1);

    return round($samples[$index], 3);
};

$runCold = static function (array $arguments) use ($iterations, $project, $home, $percentile): array {
    $samples = [];
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $environment = getenv();
    if (! is_array($environment)) {
        $environment = [];
    }
    $environment['TELL_HOME'] = $home;
    $command = [PHP_BINARY, dirname(__DIR__).'/bin/tell', ...$arguments];

    for ($index = -1; $index < $iterations; $index++) {
        $started = hrtime(true);
        $process = proc_open(
            $command,
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes,
            $project,
            $environment,
        );
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to launch Tell benchmark process.');
        }
        $status = proc_close($process);
        if ($status !== 0) {
            throw new RuntimeException("Tell benchmark command exited with status {$status}.");
        }
        if ($index >= 0) {
            $samples[] = (hrtime(true) - $started) / 1_000_000;
        }
    }

    return [
        'medianMs' => $percentile($samples, 0.5),
        'p95Ms' => $percentile($samples, 0.95),
    ];
};

$measureScans = static function (array $arguments) use ($project, $home): array {
    $scans = new StartupScanCounter;
    $factory = new TellAgentFactory(
        paths: new TellPaths(dirname(__DIR__).'/resources/agents', $home),
        driver: FakeAgentDriver::fromResponses('baseline answer'),
        startupScans: $scans,
    );
    $application = new TellApplication($factory);
    $application->setAutoExit(false);
    $output = new BufferedOutput;
    $status = $application->runArgv($arguments, $output);
    if ($status !== 0) {
        throw new RuntimeException("Tell scan probe exited with status {$status}: ".trim($output->fetch()));
    }

    return $scans->snapshot();
};

$startupBudgets = ['version' => 250.0, 'home' => 500.0];
$scanBudgets = [
    'version' => ['workspaceDiscoveries' => 0, 'agentDefinitionScans' => 0, 'composerManifestScans' => 0],
    'home' => ['workspaceDiscoveries' => 1, 'agentDefinitionScans' => 1, 'composerManifestScans' => 0],
    'agents' => ['workspaceDiscoveries' => 0, 'agentDefinitionScans' => 1, 'composerManifestScans' => 0],
    'automaticStatelessTurn' => ['workspaceDiscoveries' => 2, 'agentDefinitionScans' => 2, 'composerManifestScans' => 1],
];

try {
    $cold = [
        'version' => $runCold(['--version']),
        'home' => $runCold(['--output=json', '--dir='.$project]),
    ];
    foreach ($cold as $name => &$measurement) {
        $measurement['budgetMs'] = $startupBudgets[$name];
        $measurement['withinBudget'] = $measurement['medianMs'] <= $startupBudgets[$name];
    }
    unset($measurement);

    $measuredScans = [
        'version' => $measureScans(['tell', '--version']),
        'home' => $measureScans(['tell', '--output=json', '--dir='.$project]),
        'agents' => $measureScans(['tell', 'agents', '--json', '--dir='.$project]),
        'automaticStatelessTurn' => $measureScans(['tell', 'baseline prompt', '--output=json', '--dir='.$project]),
    ];
    $scanBudgetsMet = $measuredScans === $scanBudgets;
    $startupBudgetsMet = ! in_array(false, array_column($cold, 'withinBudget'), true);
    $result = [
        'schema' => 'tell.startup-baseline.v1',
        'php' => PHP_VERSION,
        'iterations' => $iterations,
        'coldCli' => $cold,
        'scanBudgets' => $scanBudgets,
        'measuredScans' => $measuredScans,
        'status' => [
            'startupBudgetsMet' => $startupBudgetsMet,
            'scanBudgetsMet' => $scanBudgetsMet,
        ],
    ];
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

    exit($enforce && (! $startupBudgetsMet || ! $scanBudgetsMet) ? 1 : 0);
} finally {
    $removeDirectory($root);
}
