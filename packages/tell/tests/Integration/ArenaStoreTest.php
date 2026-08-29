<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\Arena\Exception\ArenaException;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaLockException;
use Cognesy\Tell\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;
use Cognesy\Tell\Workspace\WorkspaceRepository;

beforeEach(function (): void {
    global $tellTemporaryRoots;
    $tellTemporaryRoots = [];
});

it('initializes a versioned empty main ref and publishes it with compare and swap', function (): void {
    $store = tellArenaStore('ref');
    $record = tellArenaRoot();
    $hash = $store->put($record);

    $initial = $store->readRef();
    $published = $store->compareAndSwap('main', null, $hash);

    expect($initial->head)->toBeNull()
        ->and($published->head?->equals($hash))->toBeTrue()
        ->and($store->readRef()->head?->equals($hash))->toBeTrue()
        ->and(static fn (): mixed => $store->compareAndSwap('main', null, $hash))
        ->toThrow(RefConflict::class)
        ->and(static fn (): mixed => $store->readRef('../main'))
        ->toThrow(ArenaException::class);
});

it('stores immutable objects idempotently and verifies identity before returning them', function (): void {
    $store = tellArenaStore('objects');
    $record = tellArenaRoot();

    $first = $store->put($record);
    $second = $store->put($record);
    $path = $store->objectPath($first);
    $loaded = $store->get($first);

    expect($first->equals($second))->toBeTrue()
        ->and($store->exists($first))->toBeTrue()
        ->and($loaded)->toBeInstanceOf(ConversationRoot::class)
        ->and($path)->toMatch('/objects' . preg_quote(DIRECTORY_SEPARATOR, '/') . '[a-f0-9]{2}' . preg_quote(DIRECTORY_SEPARATOR, '/') . '[a-f0-9]{62}$/')
        ->and(fileperms($path) & 0777)->toBe(0600)
        ->and(fileperms(dirname($path)) & 0777)->toBe(0700);
});

it('detects truncated and symlink-substituted objects without returning partial records', function (): void {
    $store = tellArenaStore('corrupt');
    $hash = $store->put(tellArenaRoot());
    $path = $store->objectPath($hash);
    file_put_contents($path, '{"truncated"');

    expect(static fn (): mixed => $store->get($hash))
        ->toThrow(ArenaIntegrityException::class);

    if (!function_exists('symlink')) {
        return;
    }
    $outside = tellArenaDirectory('outside-object');
    $outsideFile = $outside . '/object';
    file_put_contents($outsideFile, 'outside');
    unlink($path);
    symlink($outsideFile, $path);

    expect(static fn (): mixed => $store->exists($hash))
        ->toThrow(ArenaIntegrityException::class);
    unlink($path);
});

it('does not treat interrupted temporary files as canonical objects or move refs before publication', function (): void {
    $store = tellArenaStore('interrupted');
    $hash = $store->put(tellArenaRoot());
    $path = $store->objectPath($hash);
    $temporary = dirname($path) . '/.object.tmp-interrupted';
    file_put_contents($temporary, 'partial bytes');

    expect($store->readRef()->head)->toBeNull()
        ->and($store->get($hash))->toBeInstanceOf(ConversationRoot::class)
        ->and(is_file($temporary))->toBeTrue();
    unlink($temporary);
});

it('fails bounded ref-lock acquisition without changing the current head', function (): void {
    $store = tellArenaStore('lock-timeout');
    $hash = $store->put(tellArenaRoot());
    $lock = fopen(tellArenaWorkspacePath('locks/ref-main.lock'), 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Unable to hold Tell arena ref lock for the test.');
    }
    $contended = new FilesystemArena($GLOBALS['tellArenaWorkspace'], lockTimeoutMilliseconds: 20);

    try {
        expect(static fn (): mixed => $contended->compareAndSwap('main', null, $hash))
            ->toThrow(ArenaLockException::class)
            ->and($store->readRef()->head)->toBeNull();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

it('rejects unsafe ref substitution and malformed ref contents', function (): void {
    $store = tellArenaStore('unsafe-ref');
    $refPath = tellArenaWorkspacePath('refs/main');
    file_put_contents($refPath, '{"head":"not-a-hash","schema":1}' . "\n");

    expect(static fn (): mixed => $store->readRef())
        ->toThrow(ArenaIntegrityException::class);

    if (!function_exists('symlink')) {
        return;
    }
    $outside = tellArenaDirectory('outside-ref');
    $outsideRef = $outside . '/main';
    file_put_contents($outsideRef, "{\"head\":null,\"schema\":1}\n");
    unlink($refPath);
    symlink($outsideRef, $refPath);

    expect(static fn (): mixed => $store->readRef())
        ->toThrow(ArenaIntegrityException::class);
    unlink($refPath);
});

function tellArenaStore(string $name): FilesystemArena {
    $workspace = (new WorkspaceRepository())->initialize(tellArenaDirectory($name))->workspace;
    $GLOBALS['tellArenaWorkspacePath'] = $workspace->paths->arena;
    $GLOBALS['tellArenaWorkspace'] = $workspace;

    return new FilesystemArena($workspace);
}

function tellArenaDirectory(string $name): string {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-arena-' . $name . '-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}

function tellArenaWorkspacePath(string $relative): string {
    return $GLOBALS['tellArenaWorkspacePath'] . DIRECTORY_SEPARATOR . $relative;
}

function tellArenaRoot(): ConversationRoot {
    return new ConversationRoot(
        'conversation-001',
        [new RecordMessage(Role::System, [new TextPart('You are a durable Tell agent.')])],
    );
}
