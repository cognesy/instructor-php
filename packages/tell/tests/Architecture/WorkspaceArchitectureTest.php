<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

function tellWorkspaceSourceRoot(): string {
    return dirname(__DIR__, 2) . '/src/Workspace';
}

/** @return list<string> */
function tellWorkspaceSourceFiles(string $directory): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $directory,
        FilesystemIterator::SKIP_DOTS,
    ));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_STRING);

    return $files;
}

it('keeps the workspace root limited to lifecycle state and top-level handles', function (): void {
    $files = array_map(
        static fn (string $path): string => basename($path),
        glob(tellWorkspaceSourceRoot() . '/*.php') ?: [],
    );
    sort($files, SORT_STRING);

    expect($files)->toBe([
        'InMemoryWorkspaceModule.php',
        'TellConversation.php',
        'TellRef.php',
        'TellWorkspace.php',
        'WorkspaceException.php',
        'WorkspaceInitialization.php',
        'WorkspacePaths.php',
        'WorkspaceRepository.php',
        'WorkspaceState.php',
    ]);
});

it('keeps one cohesive branch subsystem with an explicit storage boundary', function (): void {
    $branchRoot = tellWorkspaceSourceRoot() . '/Branch';
    $branchFiles = array_map(
        static fn (string $path): string => basename($path),
        glob($branchRoot . '/*.php') ?: [],
    );
    $storageFiles = array_map(
        static fn (string $path): string => basename($path),
        glob($branchRoot . '/Storage/*.php') ?: [],
    );
    sort($branchFiles, SORT_STRING);
    sort($storageFiles, SORT_STRING);

    expect(dirname(tellWorkspaceSourceRoot()) . '/Branch')->not->toBeDirectory()
        ->and($branchFiles)->toBe([
            'BranchCatalog.php',
            'BranchName.php',
            'BranchResolver.php',
            'ResolvedBranch.php',
            'TellBranch.php',
            'TellBranchConfig.php',
            'TellBranchConfiguration.php',
            'TellBranchInfo.php',
            'TellBranchReset.php',
            'TellBranchSelection.php',
            'TellBranches.php',
        ])
        ->and($storageFiles)->toBe([
            'BranchConfigStore.php',
            'BranchCurrentSelection.php',
            'BranchCurrentSelectionStore.php',
            'BranchStore.php',
        ]);
});

it('keeps Arena independent from branch policy', function (): void {
    foreach (tellWorkspaceSourceFiles(tellWorkspaceSourceRoot() . '/Arena') as $file) {
        $source = file_get_contents($file);

        expect($source)->toBeString()
            ->and($source)->not->toContain('Cognesy\\Tell\\Workspace\\Branch\\');
    }
});

it('forbids generic manager and legacy compatibility classes', function (): void {
    foreach (tellWorkspaceSourceFiles(dirname(tellWorkspaceSourceRoot())) as $file) {
        $source = file_get_contents($file);

        expect(basename($file))->not->toMatch('/\ALegacy[A-Z]/')
            ->and($source)->toBeString()
            ->and($source)->not->toMatch('/\bclass\s+[A-Za-z0-9_]*Manager\b/')
            ->and($source)->not->toMatch('/\bclass\s+Legacy[A-Za-z0-9_]*\b/');
    }
});
