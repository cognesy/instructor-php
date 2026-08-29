<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;
use Cognesy\Tell\Workspace\Branch\BranchName;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\Branch\Storage\BranchCurrentSelectionStore;
use Cognesy\Tell\Workspace\Branch\Storage\BranchStore;
use Cognesy\Tell\Workspace\WorkspaceRepository;

beforeEach(function (): void {
    global $tellTemporaryRoots;
    $tellTemporaryRoots = [];
});

it('allows one process-level ref publisher and reports one stale conflict', function (): void {
    $root = tellArenaIntegrationDirectory('cas');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    $store = new FilesystemArena($workspace);
    $hash = $store->put(new ConversationRoot(
        'conversation-cas',
        [new RecordMessage(Role::System, [new TextPart('race')])],
    ));

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'publish', $hash->toString());

    expect([$firstOutput, $secondOutput])->toContain('published')
        ->and([$firstOutput, $secondOutput])->toContain('conflict')
        ->and([$firstExit, $secondExit])->each->toBe(0)
        ->and($store->readRef()->head?->equals($hash))->toBeTrue();
});

it('allows one process-level clearer and reports one stale conflict', function (): void {
    $root = tellArenaIntegrationDirectory('clear-cas');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    $store = new FilesystemArena($workspace);
    $hash = $store->put(new ConversationRoot(
        'conversation-clear-cas',
        [new RecordMessage(Role::System, [new TextPart('retain immutable source')])],
    ));
    $store->compareAndSwap('main', null, $hash);

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'clear', $hash->toString());

    expect([$firstOutput, $secondOutput])->toContain('cleared')
        ->and([$firstOutput, $secondOutput])->toContain('conflict')
        ->and([$firstExit, $secondExit])->each->toBe(0)
        ->and($store->readRef()->head)->toBeNull()
        ->and($store->exists($hash))->toBeTrue();
});

it('tolerates an identical object winner from separate processes', function (): void {
    $root = tellArenaIntegrationDirectory('object-race');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    $store = new FilesystemArena($workspace);

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'put');

    expect($firstExit)->toBe(0)
        ->and($secondExit)->toBe(0)
        ->and($firstOutput)->toBe($secondOutput);
    $hash = new ObjectHash($firstOutput);

    expect($store->exists($hash))->toBeTrue()
        ->and(count(glob($workspace->paths->objects . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*') ?: []))->toBe(1);
});

it('allows one process-level branch creator and reports one stable conflict', function (): void {
    $root = tellArenaIntegrationDirectory('branch-create');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    $store = new FilesystemArena($workspace);

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'branch-create', 'review');

    expect([$firstOutput, $secondOutput])->toContain('created')
        ->and([$firstOutput, $secondOutput])->toContain('conflict')
        ->and([$firstExit, $secondExit])->each->toBe(0)
        ->and($store->readRef('branches/review')->head)->toBeNull();
});

it('persists a complete current-branch selector across processes and concurrent checkouts', function (): void {
    $root = tellArenaIntegrationDirectory('checkout');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;
    $store = new FilesystemArena($workspace);
    $branches = new BranchStore($store, new BranchCurrentSelectionStore($workspace));
    $branches->create(BranchName::from('review'), $store->readRef());

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'checkout', 'review');
    [$persisted, $persistedExit] = tellArenaSingleWorker($root, 'branch-current');

    expect([$firstOutput, $secondOutput])->each->toBe('checked-out')
        ->and([$firstExit, $secondExit])->each->toBe(0)
        ->and($persistedExit)->toBe(0)
        ->and($persisted)->toBe('review')
        ->and($branches->current()->branch)->toBe('review');
});

it('allows one process-level branch config writer and reports one version conflict', function (): void {
    $root = tellArenaIntegrationDirectory('config-cas');
    $workspace = (new WorkspaceRepository())->initialize($root)->workspace;

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'config-set', 'main');
    $config = (new BranchConfigStore($workspace))->read('main');

    expect([$firstOutput, $secondOutput])->toContain('configured')
        ->and([$firstOutput, $secondOutput])->toContain('conflict')
        ->and([$firstExit, $secondExit])->each->toBe(0)
        ->and($config['version'])->toBe(1)
        ->and($config['values']['model'])->toStartWith('worker-');
});

function tellArenaIntegrationDirectory(string $name): string {
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir() . '/instructor-tell-arena-integration-' . $name . '-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}

/** @return array{0: string, 1: int} */
/** @return array{0: array{0: string, 1: int}, 1: array{0: string, 1: int}} */
function tellArenaWorkers(string $root, string $mode, ?string $hash = null): array {
    $gate = $root . '/.tell/arena/locks/test-start-' . bin2hex(random_bytes(6));
    $workers = [
        tellArenaStartWorker($root, $mode, $hash, $gate),
        tellArenaStartWorker($root, $mode, $hash, $gate),
    ];
    $readyFiles = $gate . '.*.ready';
    $deadline = hrtime(true) + 1_000_000_000;
    while (count(glob($readyFiles) ?: []) < 2 && hrtime(true) < $deadline) {
        usleep(1_000);
    }
    if (count(glob($readyFiles) ?: []) < 2) {
        throw new RuntimeException('Tell arena workers did not reach their concurrent start gate.');
    }
    touch($gate);

    return [
        tellArenaCollectWorker($workers[0]),
        tellArenaCollectWorker($workers[1]),
    ];
}

/** @return array{process: resource, pipes: array<int, resource>} */
function tellArenaStartWorker(string $root, string $mode, ?string $hash, string $gate): array {
    $command = [PHP_BINARY, __DIR__ . '/../Fixtures/arena-cas-worker.php', $root, $mode, $hash ?? ''];
    $command[] = $gate;
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Tell arena worker process.');
    }
    fclose($pipes[0]);

    return ['process' => $process, 'pipes' => $pipes];
}

/** @return array{0: string, 1: int} */
function tellArenaSingleWorker(string $root, string $mode, ?string $value = null): array {
    $command = [PHP_BINARY, __DIR__ . '/../Fixtures/arena-cas-worker.php', $root, $mode, $value ?? ''];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Tell arena worker process.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($error !== '') {
        throw new RuntimeException("Tell arena worker failed: {$error}");
    }

    return [trim($output), $exitCode];
}

/** @param array{process: resource, pipes: array<int, resource>} $worker @return array{0: string, 1: int} */
function tellArenaCollectWorker(array $worker): array {
    $pipes = $worker['pipes'];
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($worker['process']);
    if ($error !== '') {
        throw new RuntimeException("Tell arena worker failed: {$error}");
    }

    return [trim($output), $exitCode];
}
