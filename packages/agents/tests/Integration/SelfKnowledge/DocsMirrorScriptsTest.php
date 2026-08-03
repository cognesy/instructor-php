<?php declare(strict_types=1);

function runDocsScript(string $script, array $arguments): array {
    $command = [PHP_BINARY, $script, ...$arguments];
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start docs verification process.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

function docsFixture(): string {
    $root = sys_get_temp_dir() . '/agents-docs-mirror-' . bin2hex(random_bytes(6));
    mkdir($root . '/docs', 0777, true);
    mkdir($root . '/resources/docs', 0777, true);
    file_put_contents($root . '/docs/guide.md', 'source');
    file_put_contents($root . '/resources/docs/guide.md', 'source');
    file_put_contents($root . '/resources/docs-manifest.json', json_encode([
        'documents' => [[
            'source' => 'docs/guide.md',
            'destination' => 'resources/docs/guide.md',
        ]],
    ], JSON_THROW_ON_ERROR));
    return $root;
}

function removeDocsFixture(string $root): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $path) {
        match ($path->isDir()) {
            true => rmdir($path->getPathname()),
            false => unlink($path->getPathname()),
        };
    }
    rmdir($root);
}

function docsTreeSnapshot(string $root): array {
    $snapshot = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $path) {
        if ($path->isFile()) {
            $snapshot[substr($path->getPathname(), strlen($root))] = hash_file('sha256', $path->getPathname());
        }
    }
    ksort($snapshot);
    return $snapshot;
}

it('checks a synchronized mirror without writing', function () {
    $root = docsFixture();
    $script = dirname(__DIR__, 3) . '/scripts/sync-self-knowledge-docs.php';
    $before = docsTreeSnapshot($root);

    try {
        [$exit, $stdout, $stderr] = runDocsScript($script, ['--check', '--root=' . $root]);
        expect($exit)->toBe(0)
            ->and($stdout)->toContain('synchronized (1 files)')
            ->and($stderr)->toBe('')
            ->and(docsTreeSnapshot($root))->toBe($before);
    } finally {
        removeDocsFixture($root);
    }
});

it('reports each mirror failure without repairing it in check mode', function (string $failure, callable $mutate) {
    $root = docsFixture();
    $script = dirname(__DIR__, 3) . '/scripts/sync-self-knowledge-docs.php';

    try {
        $mutate($root);
        $before = docsTreeSnapshot($root);
        [$exit, , $stderr] = runDocsScript($script, ['--check', '--root=' . $root]);
        expect($exit)->toBe(1)
            ->and($stderr)->toContain($failure)
            ->and(docsTreeSnapshot($root))->toBe($before);
    } finally {
        removeDocsFixture($root);
    }
})->with([
    'missing source' => ['Missing source', static fn (string $root) => unlink($root . '/docs/guide.md')],
    'missing destination' => ['Missing destination', static fn (string $root) => unlink($root . '/resources/docs/guide.md')],
    'content drift' => ['Content drift', static fn (string $root) => file_put_contents($root . '/resources/docs/guide.md', 'drift')],
    'orphan destination' => ['Orphan destination', static fn (string $root) => file_put_contents($root . '/resources/docs/orphan.md', 'orphan')],
]);

it('writes manifest entries explicitly and then passes the same check', function () {
    $root = docsFixture();
    $script = dirname(__DIR__, 3) . '/scripts/sync-self-knowledge-docs.php';
    file_put_contents($root . '/resources/docs/guide.md', 'stale');

    try {
        [$writeExit] = runDocsScript($script, ['--write', '--root=' . $root]);
        [$checkExit] = runDocsScript($script, ['--check', '--root=' . $root]);
        expect($writeExit)->toBe(0)
            ->and($checkExit)->toBe(0)
            ->and(file_get_contents($root . '/resources/docs/guide.md'))->toBe('source');
    } finally {
        removeDocsFixture($root);
    }
});

it('verifies mirrored archive contents and rejects authored docs', function () {
    $root = docsFixture();
    $script = dirname(__DIR__, 3) . '/scripts/verify-self-knowledge-archive.php';

    try {
        removeDocsFixture($root . '/docs');
        [$pass] = runDocsScript($script, [$root]);
        mkdir($root . '/docs');
        [$fail, , $stderr] = runDocsScript($script, [$root]);
        expect($pass)->toBe(0)
            ->and($fail)->toBe(1)
            ->and($stderr)->toContain('unexpectedly contains authored docs');
    } finally {
        removeDocsFixture($root);
    }
});
