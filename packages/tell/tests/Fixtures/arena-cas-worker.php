<?php

declare(strict_types=1);

use Cognesy\Tell\Core\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;
use Cognesy\Tell\Core\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Core\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Core\Workspace\Branch\BranchName;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemBranchConfigurationStore;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemBranchSelectionStore;
use Cognesy\Tell\Core\Workspace\Branch\Storage\BranchStore;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository;

$autoload = $argv[1] ?? throw new RuntimeException('Missing Composer autoloader path.');
require $autoload;

$workspace = (new WorkspaceRepository())->discover($argv[2] ?? '');
if ($workspace === null) {
    fwrite(STDERR, "workspace not found\n");
    exit(2);
}
$store = new FilesystemArena($workspace);
$branches = new BranchStore($store, new FilesystemBranchSelectionStore($workspace));
$mode = $argv[3] ?? '';
$gate = $argv[5] ?? '';

if ($gate !== '') {
    if (file_put_contents($gate . '.' . getmypid() . '.ready', 'ready') === false) {
        fwrite(STDERR, "unable to signal arena worker readiness\n");
        exit(2);
    }
    $deadline = hrtime(true) + 1_000_000_000;
    while (!file_exists($gate) && hrtime(true) < $deadline) {
        usleep(1_000);
    }
    if (!file_exists($gate)) {
        fwrite(STDERR, "arena worker start gate timed out\n");
        exit(2);
    }
}

if ($mode === 'put') {
    echo $store->put(new ConversationRoot('race-conversation')) . "\n";
    exit(0);
}

if ($mode === 'publish') {
    try {
        $store->compareAndSwap('main', null, new ObjectHash($argv[4] ?? ''));
        echo "published\n";
    } catch (RefConflict) {
        echo "conflict\n";
    }
    exit(0);
}

if ($mode === 'clear') {
    try {
        $store->compareAndSwapToEmpty('main', new ObjectHash($argv[4] ?? ''));
        echo "cleared\n";
    } catch (RefConflict) {
        echo "conflict\n";
    }
    exit(0);
}

if ($mode === 'branch-create') {
    try {
        $branches->create(BranchName::from($argv[4] ?? ''), $store->readRef());
        echo "created\n";
    } catch (RefConflict) {
        echo "conflict\n";
    }
    exit(0);
}

if ($mode === 'checkout') {
    $branches->checkout($argv[4] ?? '');
    echo "checked-out\n";
    exit(0);
}

if ($mode === 'branch-current') {
    echo $branches->current()->branch . "\n";
    exit(0);
}

if ($mode === 'config-set') {
    try {
        (new FilesystemBranchConfigurationStore($workspace))->set($argv[4] ?? 'main', 'model', 'worker-' . getmypid(), 0);
        echo "configured\n";
    } catch (WorkspaceException) {
        echo "conflict\n";
    }
    exit(0);
}

fwrite(STDERR, "unknown mode\n");
exit(2);
