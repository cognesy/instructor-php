<?php

declare(strict_types=1);

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Workspace\ArenaRefConflict;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\WorkspaceManager;

require dirname(__DIR__, 4).'/vendor/autoload.php';

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

fwrite(STDERR, "unknown mode\n");
exit(2);
