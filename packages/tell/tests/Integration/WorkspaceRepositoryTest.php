<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceRepository;
use PHPUnit\Framework\Assert;

it('initializes a private versioned arena without rewriting an existing workspace', function (): void {
    $root = tellWorkspaceTestDirectory('init');
    $repository = new WorkspaceRepository();

    $first = $repository->initialize($root);
    $schemaMtime = filemtime($first->workspace->paths->schema);
    $mainMtime = filemtime($first->workspace->paths->mainRef);
    clearstatcache();

    $second = $repository->initialize($root);

    expect($first->created)->toBeTrue()
        ->and($first->workspace->schema)->toBe(1)
        ->and(is_dir($first->workspace->paths->objects))->toBeTrue()
        ->and(is_dir($first->workspace->paths->refs))->toBeTrue()
        ->and(is_dir($first->workspace->paths->locks))->toBeTrue()
        ->and(is_file($first->workspace->paths->mainRef))->toBeTrue()
        ->and(file_get_contents($first->workspace->paths->mainRef))->toBe("{\"head\":null,\"schema\":1}\n")
        ->and($second->created)->toBeFalse()
        ->and($second->workspace->paths->root)->toBe($first->workspace->paths->root)
        ->and(filemtime($second->workspace->paths->schema))->toBe($schemaMtime)
        ->and(filemtime($second->workspace->paths->mainRef))->toBe($mainMtime);
});

it('initializes over a .tell directory that holds something other than a workspace', function (): void {
    $root = tellWorkspaceTestDirectory('unrelated-marker');
    $repository = new WorkspaceRepository();
    // Whatever else Tell keeps under a .tell name - and $HOME/.tell is Tell's
    // own storage root - is not a half-built workspace, and treating it as one
    // left the directory permanently uninitializable.
    mkdir($root . '/.tell/blobs', 0700, true);
    file_put_contents($root . '/.tell/blobs/kept.txt', "kept\n");

    $initialized = $repository->initialize($root);

    expect($initialized->created)->toBeTrue()
        ->and($initialized->workspace->schema)->toBe(1)
        ->and(is_file($root . '/.tell/blobs/kept.txt'))->toBeTrue()
        ->and($repository->discover($root))->not->toBeNull();
});

it('discovers the nearest valid workspace from nested paths and stops at the filesystem root', function (): void {
    $outer = tellWorkspaceTestDirectory('outer');
    $inner = $outer . '/nested';
    mkdir($inner, 0700, true);
    $repository = new WorkspaceRepository();
    $repository->initialize($outer);
    $innerWorkspace = $repository->initialize($inner)->workspace;
    mkdir($inner . '/deeper/path', 0700, true);
    $outside = tellWorkspaceTestDirectory('no-marker');

    expect($repository->discover($inner . '/deeper/path'))->not->toBeNull()
        ->and($repository->discover($inner . '/deeper/path')?->paths->root)->toBe($innerWorkspace->paths->root)
        ->and($repository->discover($outside))->toBeNull();
});

it('walks past a .tell directory that is not a workspace', function (): void {
    // Tell's own user storage root is $HOME/.tell, so the first run writes an
    // execution trace there and creates the directory. Discovery must not then
    // mistake $HOME for an initialized workspace for every project under it.
    $home = tellWorkspaceTestDirectory('fake-home');
    mkdir($home . '/.tell/logs/executions', 0700, true);
    $project = $home . '/project';
    mkdir($project, 0700, true);
    $repository = new WorkspaceRepository();

    expect($repository->discover($project))->toBeNull();
});

it('still discovers a real workspace above a .tell directory that is not one', function (): void {
    $root = tellWorkspaceTestDirectory('real-above');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    $nested = $root . '/storage/project';
    mkdir($nested, 0700, true);
    mkdir($root . '/storage/.tell/logs', 0700, true);
    $repository = new WorkspaceRepository();

    expect($repository->discover($nested)?->paths->root)->toBe($workspace->paths->root);
});

it('still reports a workspace whose arena is broken', function (): void {
    $root = tellWorkspaceTestDirectory('broken-arena');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    // The schema identifies the workspace; a missing refs directory beneath it
    // is corruption and must stay loud rather than being silently walked past.
    array_map('unlink', glob($workspace->paths->refs . '/*') ?: []);
    rmdir($workspace->paths->refs);

    expect(static fn () => (new WorkspaceRepository())->discover($root))
        ->toThrow(WorkspaceException::class, 'workspace refs is not a directory');
});

it('does not create files or directories while discovering a workspace', function (): void {
    $root = tellWorkspaceTestDirectory('read-only');
    $nested = $root . '/a/b';
    mkdir($nested, 0700, true);
    $before = tellDirectorySnapshot($root);

    $workspace = (new WorkspaceRepository())->discover($nested);

    expect($workspace)->toBeNull()
        ->and(tellDirectorySnapshot($root))->toBe($before);
});

it('refuses malformed, incompatible, and unsafe marker layouts without mutation', function (Closure $arrange): void {
    $root = tellWorkspaceTestDirectory('invalid');
    $arrange($root);
    $before = tellDirectorySnapshot($root);

    expect(static fn (): mixed => (new WorkspaceRepository())->initialize($root))
        ->toThrow(WorkspaceException::class);
    expect(tellDirectorySnapshot($root))->toBe($before);
})->with([
    'marker is a file' => static function (string $root): void {
        file_put_contents($root . '/.tell', 'not a directory');
    },
    'arena is a file' => static function (string $root): void {
        mkdir($root . '/.tell', 0700, true);
        file_put_contents($root . '/.tell/arena', 'not a directory');
    },
    'unsupported schema' => static function (string $root): void {
        mkdir($root . '/.tell/arena/objects', 0700, true);
        mkdir($root . '/.tell/arena/refs', 0700, true);
        mkdir($root . '/.tell/arena/locks', 0700, true);
        file_put_contents($root . '/.tell/arena/refs/main', '');
        file_put_contents($root . '/.tell/arena/schema', "2\n");
    },
    'partial arena' => static function (string $root): void {
        mkdir($root . '/.tell/arena/objects', 0700, true);
        file_put_contents($root . '/.tell/arena/schema', "1\n");
    },
]);

it('refuses a symlinked workspace marker without following it', function (): void {
    if (!function_exists('symlink')) {
        Assert::markTestSkipped('Symlink support is unavailable.');
    }
    $root = tellWorkspaceTestDirectory('symlink');
    $outside = tellWorkspaceTestDirectory('outside');
    symlink($outside, $root . '/.tell');
    $before = tellDirectorySnapshot($root);

    expect(static fn (): mixed => (new WorkspaceRepository())->initialize($root))
        ->toThrow(WorkspaceException::class, 'symlink');
    expect(tellDirectorySnapshot($root))->toBe($before)
        ->and(is_dir($outside . '/arena'))->toBeFalse();
    unlink($root . '/.tell');
});

it('creates private workspace files and directories where POSIX modes are available', function (): void {
    $workspace = (new WorkspaceRepository())->initialize(tellWorkspaceTestDirectory('permissions'))->workspace;

    expect(fileperms($workspace->paths->marker) & 0777)->toBe(0700)
        ->and(fileperms($workspace->paths->arena) & 0777)->toBe(0700)
        ->and(fileperms($workspace->paths->schema) & 0777)->toBe(0600)
        ->and(fileperms($workspace->paths->mainRef) & 0777)->toBe(0600);
});

function tellWorkspaceTestDirectory(string $name): string {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-workspace-' . $name . '-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}

/** @return list<string> */
function tellDirectorySnapshot(string $directory): array {
    $items = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $item) {
        $items[] = str_replace($directory . '/', '', $item->getPathname());
    }
    sort($items);

    return $items;
}
