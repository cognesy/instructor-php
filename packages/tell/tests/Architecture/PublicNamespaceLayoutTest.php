<?php

declare(strict_types=1);

use Cognesy\Tell\Composition\Standalone\Host\TellHost;
use Cognesy\Tell\Composition\Standalone\Host\TellHostBuilder;
use Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost;
use Cognesy\Tell\Adapter\Console\Symfony\SymfonyConsoleApplicationBuilder;
use Cognesy\Tell\Adapter\Console\Symfony\SymfonyConsoleApplicationRunner;
use Cognesy\Tell\Adapter\Console\Symfony\TellConsoleApplication;
use Cognesy\Tell\Data\TellShellJobApproval;
use Cognesy\Tell\Data\TellShellJobEvent;
use Cognesy\Tell\Data\TellShellJobHealth;
use Cognesy\Tell\Data\TellShellJobOutput;
use Cognesy\Tell\Data\TellShellJobOutputChunk;
use Cognesy\Tell\Data\TellShellJobRequest;
use Cognesy\Tell\Data\TellShellJobSnapshot;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;
use Cognesy\Tell\Core\Discovery\TellCatalogue;
use Cognesy\Tell\Composition\Standalone\Profile\ShellJob\TellShellJobHost;
use Cognesy\Tell\Composition\Standalone\Profile\ShellJob\TellShellJobHostBuilder;
use Cognesy\Tell\Composition\Standalone\Profile\ShellJob\StandardTellShellJobProfile;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobPolicy;
use Cognesy\Tell\Data\TellShellJobState;
use Cognesy\Tell\Core\Tool\TellTools;
use Cognesy\Tell\Core\Workspace\Branch\TellBranch;
use Cognesy\Tell\Data\TellBranchConfig;
use Cognesy\Tell\Core\Workspace\Branch\TellBranchConfiguration;
use Cognesy\Tell\Core\Workspace\Branch\TellBranches;
use Cognesy\Tell\Data\TellBranchInfo;
use Cognesy\Tell\Data\TellBranchReset;
use Cognesy\Tell\Data\TellBranchSelection;
use Cognesy\Tell\Core\Workspace\TellRef;

it('keeps only the facade in the root namespace', function (): void {
    $rootFiles = array_map(
        static fn (string $path): string => basename($path),
        glob(dirname(__DIR__, 2) . '/src/*.php') ?: [],
    );
    sort($rootFiles);

    expect($rootFiles)->toBe(['Tell.php']);
});

it('keeps boundary data in one flat Data namespace', function (): void {
    $dataRoot = dirname(__DIR__, 2) . '/src/Data';
    $files = glob($dataRoot . '/*.php') ?: [];

    expect(glob($dataRoot . '/*', GLOB_ONLYDIR) ?: [])->toBe([])
        ->and($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)->toBeString()
            ->and($source)->toContain('namespace Cognesy\\Tell\\Data;');
    }
});

it('keeps configuration independent of console input', function (): void {
    $configuration = '';
    $root = dirname(__DIR__, 2) . '/src/Capability/Configuration';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        $configuration .= is_string($source) ? $source : '';
    }

    expect($configuration)->not->toContain('Symfony\\Component\\Console')
        ->not->toContain('Cognesy\\Tell\\Console\\');
});

it('keeps the source root limited to the five architecture categories and test support', function (): void {
    $sourceRoot = dirname(__DIR__, 2) . '/src';
    $directories = array_map(
        static fn (string $path): string => basename($path),
        glob($sourceRoot . '/*', GLOB_ONLYDIR) ?: [],
    );
    sort($directories, SORT_STRING);

    expect($directories)->toBe(['Adapter', 'Capability', 'Composition', 'Core', 'Data', 'Testing']);
});

it('does not recreate obsolete catch-all namespaces', function (): void {
    $sourceRoot = dirname(__DIR__, 2) . '/src';

    expect($sourceRoot . '/Canonical')->not->toBeDirectory()
        ->and($sourceRoot . '/Diagnostics')->not->toBeDirectory()
        ->and($sourceRoot . '/Resource')->not->toBeDirectory()
        ->and($sourceRoot . '/Contracts/Data')->not->toBeDirectory()
        ->and($sourceRoot . '/Contracts/Collections')->not->toBeDirectory();
});

it('aligns cohesive public class families with their PSR-4 namespaces', function (): void {
    $classes = [
        TellBranch::class,
        TellBranchConfig::class,
        TellBranchConfiguration::class,
        TellBranchInfo::class,
        TellBranchReset::class,
        TellBranchSelection::class,
        TellBranches::class,
        TellRef::class,
        StandaloneTellHost::class,
        TellHost::class,
        TellHostBuilder::class,
        TellConsoleApplication::class,
        SymfonyConsoleApplicationBuilder::class,
        SymfonyConsoleApplicationRunner::class,
        TellCatalogue::class,
        TellShellJobEvent::class,
        TellShellJobHealth::class,
        TellShellJobHost::class,
        TellShellJobHostBuilder::class,
        StandardTellShellJobProfile::class,
        TellShellJobApproval::class,
        TellShellJobOutput::class,
        TellShellJobOutputChunk::class,
        TellShellJobPolicy::class,
        TellShellJobRequest::class,
        TellShellJobSnapshot::class,
        TellShellJobState::class,
        TellToolRequest::class,
        TellToolResult::class,
        TellTools::class,
    ];
    $source = realpath(dirname(__DIR__, 2) . '/src');

    expect($source)->toBeString();
    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        $relativeClass = substr($class, strlen('Cognesy\\Tell\\'));
        $expected = $source . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        expect($reflection->getFileName())->toBe($expected);
    }
});
