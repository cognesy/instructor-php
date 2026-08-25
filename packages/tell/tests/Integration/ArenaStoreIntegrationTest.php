<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\WorkspaceManager;

beforeEach(function (): void {
    global $tellTemporaryRoots;
    $tellTemporaryRoots = [];
});

it('allows one process-level ref publisher and reports one stale conflict', function (): void {
    $root = tellArenaIntegrationDirectory('cas');
    $workspace = (new WorkspaceManager)->initialize($root)->workspace;
    $store = new ArenaStore($workspace);
    $hash = $store->put(new CanonicalConversationRoot(
        'conversation-cas',
        [new CanonicalMessage(CanonicalRole::System, [new CanonicalTextPart('race')])],
    ));

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'publish', $hash->toString());

    expect([$firstOutput, $secondOutput])->toContain('published')
        ->and([$firstOutput, $secondOutput])->toContain('conflict')
        ->and([$firstExit, $secondExit])->each->toBe(0)
        ->and($store->readRef()->head?->equals($hash))->toBeTrue();
});

it('allows one process-level clearer and reports one stale conflict', function (): void {
    $root = tellArenaIntegrationDirectory('clear-cas');
    $workspace = (new WorkspaceManager)->initialize($root)->workspace;
    $store = new ArenaStore($workspace);
    $hash = $store->put(new CanonicalConversationRoot(
        'conversation-clear-cas',
        [new CanonicalMessage(CanonicalRole::System, [new CanonicalTextPart('retain immutable source')])],
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
    $workspace = (new WorkspaceManager)->initialize($root)->workspace;
    $store = new ArenaStore($workspace);

    [[$firstOutput, $firstExit], [$secondOutput, $secondExit]] = tellArenaWorkers($root, 'put');

    expect($firstExit)->toBe(0)
        ->and($secondExit)->toBe(0)
        ->and($firstOutput)->toBe($secondOutput);
    $hash = new CanonicalHash($firstOutput);

    expect($store->exists($hash))->toBeTrue()
        ->and(count(glob($workspace->paths->objects.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*') ?: []))->toBe(1);
});

function tellArenaIntegrationDirectory(string $name): string
{
    global $tellTemporaryRoots;

    $root = sys_get_temp_dir().'/instructor-tell-arena-integration-'.$name.'-'.bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    $tellTemporaryRoots[] = $root;

    return $root;
}

/** @return array{0: string, 1: int} */
/** @return array{0: array{0: string, 1: int}, 1: array{0: string, 1: int}} */
function tellArenaWorkers(string $root, string $mode, ?string $hash = null): array
{
    $gate = $root.'/.tell/arena/locks/test-start-'.bin2hex(random_bytes(6));
    $workers = [
        tellArenaStartWorker($root, $mode, $hash, $gate),
        tellArenaStartWorker($root, $mode, $hash, $gate),
    ];
    $readyFiles = $gate.'.*.ready';
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
function tellArenaStartWorker(string $root, string $mode, ?string $hash, string $gate): array
{
    $command = [PHP_BINARY, __DIR__.'/../Fixtures/arena-cas-worker.php', $root, $mode, $hash ?? ''];
    $command[] = $gate;
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start Tell arena worker process.');
    }
    fclose($pipes[0]);

    return ['process' => $process, 'pipes' => $pipes];
}

/** @param array{process: resource, pipes: array<int, resource>} $worker @return array{0: string, 1: int} */
function tellArenaCollectWorker(array $worker): array
{
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
