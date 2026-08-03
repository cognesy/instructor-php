<?php declare(strict_types=1);

function argumentValue(string $name): ?string
{
    foreach ($_SERVER['argv'] as $argument) {
        if (str_starts_with($argument, $name . '=')) {
            return substr($argument, strlen($name) + 1);
        }
    }
    return null;
}

function packageRoot(): string
{
    $root = argumentValue('--root') ?? dirname(__DIR__);
    $resolved = realpath($root);
    return is_string($resolved) ? $resolved : rtrim($root, DIRECTORY_SEPARATOR);
}

/** @return list<array{source: string, destination: string}> */
function manifest(string $root): array
{
    $path = $root . '/resources/docs-manifest.json';
    $json = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($json)) {
        throw new RuntimeException("Missing docs manifest: {$path}");
    }
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $documents = $decoded['documents'] ?? null;
    if (!is_array($documents)) {
        throw new RuntimeException("Invalid docs manifest: {$path}");
    }

    $entries = [];
    foreach ($documents as $document) {
        if (!is_array($document)) {
            throw new RuntimeException('Every docs manifest entry must be an object.');
        }
        $source = $document['source'] ?? null;
        $destination = $document['destination'] ?? null;
        if (!is_string($source) || !is_string($destination)) {
            throw new RuntimeException('Every docs manifest entry needs string source and destination paths.');
        }
        validateRelativePath($source, 'docs/');
        validateRelativePath($destination, 'resources/docs/');
        $entries[] = ['source' => $source, 'destination' => $destination];
    }
    return $entries;
}

function validateRelativePath(string $path, string $prefix): void
{
    $invalid = $path === ''
        || str_starts_with($path, '/')
        || str_contains($path, '..')
        || !str_starts_with($path, $prefix);
    if ($invalid) {
        throw new RuntimeException("Unsafe docs manifest path: {$path}");
    }
}

/** @param list<array{source: string, destination: string}> $entries */
function writeMirror(string $root, array $entries): void
{
    foreach ($entries as $entry) {
        $source = $root . '/' . $entry['source'];
        $destination = $root . '/' . $entry['destination'];
        if (!is_file($source)) {
            throw new RuntimeException("Missing source: {$entry['source']}");
        }
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create mirror directory: {$directory}");
        }
        if (!copy($source, $destination)) {
            throw new RuntimeException("Cannot copy {$entry['source']} to {$entry['destination']}");
        }
    }
}

/**
 * @param list<array{source: string, destination: string}> $entries
 * @return list<string>
 */
function checkMirror(string $root, array $entries): array
{
    $errors = [];
    $destinations = [];
    foreach ($entries as $entry) {
        $source = $root . '/' . $entry['source'];
        $destination = $root . '/' . $entry['destination'];
        $destinations[] = $entry['destination'];
        if (!is_file($source)) {
            $errors[] = "Missing source: {$entry['source']}";
            continue;
        }
        if (!is_file($destination)) {
            $errors[] = "Missing destination: {$entry['destination']}";
            continue;
        }
        if (hash_file('sha256', $source) !== hash_file('sha256', $destination)) {
            $errors[] = "Content drift: {$entry['destination']}";
        }
    }

    foreach (mirrorFiles($root . '/resources/docs', $root) as $file) {
        if (!in_array($file, $destinations, true)) {
            $errors[] = "Orphan destination: {$file}";
        }
    }
    return $errors;
}

/** @return list<string> */
function mirrorFiles(string $mirrorRoot, string $packageRoot): array
{
    if (!is_dir($mirrorRoot)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($mirrorRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $files[] = ltrim(substr($file->getPathname(), strlen($packageRoot)), DIRECTORY_SEPARATOR);
    }
    sort($files);
    return $files;
}

try {
    $root = packageRoot();
    $entries = manifest($root);
    $write = in_array('--write', $_SERVER['argv'], true);
    $check = in_array('--check', $_SERVER['argv'], true);
    if ($write === $check) {
        throw new RuntimeException('Choose exactly one mode: --write or --check.');
    }
    if ($write) {
        writeMirror($root, $entries);
    }
    $errors = checkMirror($root, $entries);
    if ($errors !== []) {
        fwrite(STDERR, implode("\n", $errors) . "\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf("Self-knowledge docs mirror is synchronized (%d files).\n", count($entries)));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
