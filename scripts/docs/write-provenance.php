#!/usr/bin/env php
<?php declare(strict_types=1);

$sourceSha = $argv[1] ?? '';
$targetDirectories = array_slice($argv, 2);

if (!preg_match('/^[a-f0-9]{40}$/', $sourceSha)) {
    fwrite(STDERR, "Source SHA must be a full 40-character Git commit hash.\n");
    exit(1);
}

if ($targetDirectories === []) {
    fwrite(STDERR, "At least one provenance target directory is required.\n");
    exit(1);
}

$payload = json_encode([
    'schemaVersion' => 1,
    'repository' => 'cognesy/instructor-php',
    'sourceSha' => $sourceSha,
    'sourceUrl' => "https://github.com/cognesy/instructor-php/commit/{$sourceSha}",
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

foreach ($targetDirectories as $directory) {
    if (!is_dir($directory)) {
        fwrite(STDERR, "Provenance target directory is missing: {$directory}\n");
        exit(1);
    }

    $result = file_put_contents($directory . '/deployment.json', $payload);
    if ($result === false) {
        fwrite(STDERR, "Could not write provenance to: {$directory}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Stamped documentation artifacts with source SHA {$sourceSha}.\n");
