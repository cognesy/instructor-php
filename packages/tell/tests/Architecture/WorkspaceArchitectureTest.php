<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

function tellWorkspaceSourceRoot(): string {
    return dirname(__DIR__, 2) . '/src/Core/Workspace';
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

it('keeps workspace policy in Core and provider mechanics in isolated capabilities', function (): void {
    $branchRoot = tellWorkspaceSourceRoot() . '/Branch';
    $branchFiles = glob($branchRoot . '/*.php') ?: [];
    $storageFiles = glob($branchRoot . '/Storage/*.php') ?: [];
    $filesystemFiles = tellWorkspaceSourceFiles(dirname(__DIR__, 2) . '/src/Capability/Workspace/Filesystem');
    $memoryFiles = tellWorkspaceSourceFiles(dirname(__DIR__, 2) . '/src/Capability/Workspace/Memory');

    expect(dirname(__DIR__, 2) . '/src/Workspace')->not->toBeDirectory()
        ->and($branchFiles)->not->toBeEmpty()
        ->and($storageFiles)->not->toBeEmpty()
        ->and($filesystemFiles)->not->toBeEmpty()
        ->and($memoryFiles)->not->toBeEmpty();

    foreach ($branchFiles as $file) {
        expect(file_get_contents($file))->toContain('namespace Cognesy\\Tell\\Core\\Workspace\\Branch;');
    }
    foreach ($storageFiles as $file) {
        expect(file_get_contents($file))->toContain('namespace Cognesy\\Tell\\Core\\Workspace\\Branch\\Storage;');
    }
    foreach ($filesystemFiles as $file) {
        expect(file_get_contents($file))->toContain('namespace Cognesy\\Tell\\Capability\\Workspace\\Filesystem;');
    }
    foreach ($memoryFiles as $file) {
        expect(file_get_contents($file))->toContain('namespace Cognesy\\Tell\\Capability\\Workspace\\Memory;');
    }
    foreach (tellWorkspaceSourceFiles(tellWorkspaceSourceRoot()) as $file) {
        expect(file_get_contents($file))->not->toContain('Cognesy\\Tell\\Capability\\Workspace\\');
    }
});

it('keeps Arena independent from branch policy', function (): void {
    foreach (tellWorkspaceSourceFiles(tellWorkspaceSourceRoot() . '/Arena') as $file) {
        $source = file_get_contents($file);

        expect($source)->toBeString()
            ->and($source)->not->toContain('Cognesy\\Tell\\Core\\Workspace\\Branch\\');
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
