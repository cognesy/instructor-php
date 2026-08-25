<?php

declare(strict_types=1);

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Workspace\ArenaRefConflict;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchName;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceManager;

$packageRoot = dirname(__DIR__, 2);
$monorepoRoot = dirname(__DIR__, 4);
$autoload = is_dir($monorepoRoot.'/packages/tell')
    ? $monorepoRoot.'/vendor/autoload.php'
    : $packageRoot.'/vendor/autoload.php';

require $autoload;

$workspace = (new WorkspaceManager)->discover($argv[1] ?? '');
if ($workspace === null) {
    fwrite(STDERR, "workspace not found\n");
    exit(2);
}
$store = new ArenaStore($workspace);
$mode = $argv[2] ?? '';
$gate = $argv[4] ?? '';

if ($gate !== '') {
    if (file_put_contents($gate.'.'.getmypid().'.ready', 'ready') === false) {
        fwrite(STDERR, "unable to signal arena worker readiness\n");
        exit(2);
    }
    $deadline = hrtime(true) + 1_000_000_000;
    while (! file_exists($gate) && hrtime(true) < $deadline) {
        usleep(1_000);
    }
    if (! file_exists($gate)) {
        fwrite(STDERR, "arena worker start gate timed out\n");
        exit(2);
    }
}

if ($mode === 'put') {
    echo $store->put(new CanonicalConversationRoot('race-conversation'))."\n";
    exit(0);
}

if ($mode === 'publish') {
    try {
        $store->compareAndSwap('main', null, new CanonicalHash($argv[3] ?? ''));
        echo "published\n";
    } catch (ArenaRefConflict) {
        echo "conflict\n";
    }
    exit(0);
}

if ($mode === 'clear') {
    try {
        $store->compareAndSwapToEmpty('main', new CanonicalHash($argv[3] ?? ''));
        echo "cleared\n";
    } catch (ArenaRefConflict) {
        echo "conflict\n";
    }
    exit(0);
}

if ($mode === 'branch-create') {
    try {
        $store->createBranch(BranchName::from($argv[3] ?? ''), $store->readRef());
        echo "created\n";
    } catch (ArenaRefConflict) {
        echo "conflict\n";
    }
    exit(0);
}

if ($mode === 'checkout') {
    $store->checkout($argv[3] ?? '');
    echo "checked-out\n";
    exit(0);
}

if ($mode === 'branch-current') {
    echo $store->readCurrentBranch()->branch."\n";
    exit(0);
}

if ($mode === 'config-set') {
    try {
        (new BranchConfigStore($workspace))->set($argv[3] ?? 'main', 'model', 'worker-'.getmypid(), 0);
        echo "configured\n";
    } catch (WorkspaceException) {
        echo "conflict\n";
    }
    exit(0);
}

fwrite(STDERR, "unknown mode\n");
exit(2);
