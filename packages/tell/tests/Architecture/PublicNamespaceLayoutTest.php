<?php

declare(strict_types=1);

use Cognesy\Tell\Composition\TellHost;
use Cognesy\Tell\Composition\TellHostBuilder;
use Cognesy\Tell\Data\TellShellJobApproval;
use Cognesy\Tell\Data\TellShellJobEvent;
use Cognesy\Tell\Data\TellShellJobHealth;
use Cognesy\Tell\Data\TellShellJobOutput;
use Cognesy\Tell\Data\TellShellJobOutputChunk;
use Cognesy\Tell\Data\TellShellJobRequest;
use Cognesy\Tell\Data\TellShellJobSnapshot;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Data\TellToolResult;
use Cognesy\Tell\Discovery\TellCatalogue;
use Cognesy\Tell\Shell\TellShellJobHost;
use Cognesy\Tell\Shell\TellShellJobHostBuilder;
use Cognesy\Tell\Shell\TellShellJobPolicy;
use Cognesy\Tell\Shell\TellShellJobState;
use Cognesy\Tell\Tool\TellTools;
use Cognesy\Tell\Workspace\Branch\TellBranch;
use Cognesy\Tell\Workspace\Branch\TellBranchConfig;
use Cognesy\Tell\Workspace\Branch\TellBranchConfiguration;
use Cognesy\Tell\Workspace\Branch\TellBranches;
use Cognesy\Tell\Workspace\Branch\TellBranchInfo;
use Cognesy\Tell\Workspace\Branch\TellBranchReset;
use Cognesy\Tell\Workspace\Branch\TellBranchSelection;
use Cognesy\Tell\Workspace\TellRef;

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
    $files = array_map(
        static fn (string $path): string => basename($path),
        glob($dataRoot . '/*.php') ?: [],
    );
    sort($files, SORT_STRING);

    expect(glob($dataRoot . '/*', GLOB_ONLYDIR) ?: [])->toBe([])
        ->and($files)->toBe([
            'TellClearResult.php',
            'TellCommandDescriptor.php',
            'TellCommandDescriptors.php',
            'TellCompactionResult.php',
            'TellContext.php',
            'TellConversationView.php',
            'TellDiagnostic.php',
            'TellEffectiveConfiguration.php',
            'TellEventEnvelope.php',
            'TellExecutionMode.php',
            'TellExtensionCatalogue.php',
            'TellExtensionDescriptor.php',
            'TellExtensionDescriptors.php',
            'TellExtensionKind.php',
            'TellHostDescription.php',
            'TellProgress.php',
            'TellRequest.php',
            'TellResolvedPaths.php',
            'TellResult.php',
            'TellShellJobApproval.php',
            'TellShellJobEvent.php',
            'TellShellJobHealth.php',
            'TellShellJobOutput.php',
            'TellShellJobOutputChunk.php',
            'TellShellJobRequest.php',
            'TellShellJobSnapshot.php',
            'TellToolRequest.php',
            'TellToolResult.php',
            'TellWorkspaceInfo.php',
        ]);

    foreach ($files as $file) {
        $source = file_get_contents($dataRoot . '/' . $file);

        expect($source)->toBeString()
            ->and($source)->toContain('namespace Cognesy\\Tell\\Data;');
    }
});

it('keeps Runtime limited to execution machinery', function (): void {
    $runtimeFiles = array_map(
        static fn (string $path): string => basename($path),
        glob(dirname(__DIR__, 2) . '/src/Runtime/*.php') ?: [],
    );
    sort($runtimeFiles, SORT_STRING);

    expect($runtimeFiles)->toBe([
        'CanOpenTellRuntime.php',
        'CanReadTellClock.php',
        'DefaultTellRunner.php',
        'StandardTellAgentBuilder.php',
        'SystemTellClock.php',
        'TellAgentFactory.php',
        'TellDelegationScope.php',
        'TellDiagnostics.php',
        'TellExecutionBudgetHook.php',
        'TellRun.php',
        'TellRunOutcome.php',
        'TellRuntime.php',
        'TellSignalCancellationSource.php',
        'TellSpillToolOutputHook.php',
        'TellSubagentExecutor.php',
        'ToolOutputSpill.php',
    ]);
});

it('keeps configuration independent of console input', function (): void {
    $configuration = '';
    foreach (glob(dirname(__DIR__, 2) . '/src/Configuration/*.php') ?: [] as $file) {
        $source = file_get_contents($file);
        $configuration .= is_string($source) ? $source : '';
    }

    expect($configuration)->not->toContain('Symfony\\Component\\Console')
        ->not->toContain('Cognesy\\Tell\\Console\\');
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
        TellHost::class,
        TellHostBuilder::class,
        TellCatalogue::class,
        TellShellJobEvent::class,
        TellShellJobHealth::class,
        TellShellJobHost::class,
        TellShellJobHostBuilder::class,
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
