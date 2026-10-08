<?php

declare(strict_types=1);

it('keeps batch inference outside the existing production source graph', function () {
    $root = dirname(__DIR__, 5);
    $references = [];

    foreach (glob($root.'/packages/*/src') ?: [] as $sourceRoot) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || str_contains($path, '/polyglot/src/BatchInference/')) {
                continue;
            }
            foreach (token_get_all((string) file_get_contents($path)) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
                    continue;
                }
                if (str_starts_with(ltrim($token[1], '\\'), 'Cognesy\\Polyglot\\BatchInference\\')) {
                    $references[] = $path.':'.$token[2];
                }
            }
        }
    }

    expect($references)->toBe([]);
});
