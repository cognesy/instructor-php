<?php

declare(strict_types=1);

it('keeps only the established facade and request vocabulary in the root namespace', function (): void {
    $rootFiles = array_map(
        static fn (string $path): string => basename($path),
        glob(dirname(__DIR__, 2).'/src/*.php') ?: [],
    );
    sort($rootFiles);

    expect($rootFiles)->toBe([
        'Tell.php',
        'TellApplication.php',
        'TellClearResult.php',
        'TellCommand.php',
        'TellCompactionResult.php',
        'TellContext.php',
        'TellConversation.php',
        'TellConversationView.php',
        'TellEvent.php',
        'TellExecutionMode.php',
        'TellProgress.php',
        'TellReasoningEffort.php',
        'TellRequest.php',
        'TellResult.php',
        'TellWorkspace.php',
        'TellWorkspaceInfo.php',
    ]);
});

it('aligns cohesive public class families with their PSR-4 namespaces', function (): void {
    $classes = [
        Cognesy\Tell\Branch\TellBranch::class,
        Cognesy\Tell\Branch\TellBranchConfig::class,
        Cognesy\Tell\Branch\TellBranchConfiguration::class,
        Cognesy\Tell\Branch\TellBranchInfo::class,
        Cognesy\Tell\Branch\TellBranchReset::class,
        Cognesy\Tell\Branch\TellBranchSelection::class,
        Cognesy\Tell\Branch\TellBranches::class,
        Cognesy\Tell\Branch\TellRef::class,
        Cognesy\Tell\Composition\TellHost::class,
        Cognesy\Tell\Composition\TellHostBuilder::class,
        Cognesy\Tell\Discovery\TellCatalogue::class,
        Cognesy\Tell\Resource\TellResourceEvent::class,
        Cognesy\Tell\Resource\TellResourceHealth::class,
        Cognesy\Tell\Resource\TellResourceHost::class,
        Cognesy\Tell\Resource\TellResourceHostBuilder::class,
        Cognesy\Tell\Shell\TellShellJobApproval::class,
        Cognesy\Tell\Shell\TellShellJobOutput::class,
        Cognesy\Tell\Shell\TellShellJobOutputChunk::class,
        Cognesy\Tell\Shell\TellShellJobPolicy::class,
        Cognesy\Tell\Shell\TellShellJobRequest::class,
        Cognesy\Tell\Shell\TellShellJobSnapshot::class,
        Cognesy\Tell\Shell\TellShellJobState::class,
        Cognesy\Tell\Tool\TellToolRequest::class,
        Cognesy\Tell\Tool\TellToolResult::class,
        Cognesy\Tell\Tool\TellTools::class,
    ];
    $source = realpath(dirname(__DIR__, 2).'/src');

    expect($source)->toBeString();
    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        $relativeClass = substr($class, strlen('Cognesy\\Tell\\'));
        $expected = $source.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass).'.php';

        expect($reflection->getFileName())->toBe($expected);
    }
});
