<?php declare(strict_types=1);

$root = $_SERVER['argv'][1] ?? '';
$manifestPath = rtrim($root, DIRECTORY_SEPARATOR) . '/resources/docs-manifest.json';

try {
    if (!is_file($manifestPath)) {
        throw new RuntimeException("Archive is missing manifest: {$manifestPath}");
    }
    $json = file_get_contents($manifestPath);
    $manifest = json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    $documents = $manifest['documents'] ?? [];
    if (!is_array($documents) || $documents === []) {
        throw new RuntimeException('Archive docs manifest has no documents.');
    }
    foreach ($documents as $document) {
        $destination = is_array($document) ? ($document['destination'] ?? null) : null;
        if (!is_string($destination) || !is_file($root . '/' . $destination)) {
            throw new RuntimeException('Archive is missing destination: ' . (string) $destination);
        }
    }
    if (is_dir($root . '/docs')) {
        throw new RuntimeException('Archive unexpectedly contains authored docs/.');
    }
    fwrite(STDOUT, sprintf("Package archive contains %d mirrored docs and excludes authored docs/.\n", count($documents)));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
