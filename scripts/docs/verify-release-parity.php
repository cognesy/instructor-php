#!/usr/bin/env php
<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$options = getopt('', [
    'release:',
    'source-dir:',
    'mintlify-dir:',
    'mkdocs-dir:',
    'mintlify-config:',
    'mkdocs-config:',
]);

$sourceDir = $options['source-dir'] ?? 'docs/release-notes';
$mintlifyDir = $options['mintlify-dir'] ?? 'builds/docs-build/release-notes';
$mkdocsDir = $options['mkdocs-dir'] ?? 'builds/docs-mkdocs/release-notes';
$mintlifyConfig = $options['mintlify-config'] ?? 'builds/docs-build/mint.json';
$mkdocsConfig = $options['mkdocs-config'] ?? 'builds/mkdocs.yml';
$requestedRelease = normalizeRelease($options['release'] ?? '');

$sourceFiles = glob($sourceDir . '/v*.mdx') ?: [];
$releaseNames = array_map(
    static fn(string $path): string => pathinfo($path, PATHINFO_FILENAME),
    $sourceFiles,
);
sort($releaseNames, SORT_NATURAL | SORT_FLAG_CASE);

$errors = [];
if ($releaseNames === []) {
    $errors[] = "No release notes found in {$sourceDir}";
}

if ($requestedRelease !== '' && !in_array($requestedRelease, $releaseNames, true)) {
    $errors[] = "Requested release {$requestedRelease} has no authored release note";
}

$mintlifyNavigation = loadMintlifyNavigation($mintlifyConfig, $errors);
$mkdocsNavigation = loadMkDocsNavigation($mkdocsConfig, $errors);

foreach ($releaseNames as $releaseName) {
    assertFileExists("{$mintlifyDir}/{$releaseName}.mdx", 'Mintlify page', $errors);
    assertFileExists("{$mkdocsDir}/{$releaseName}.md", 'MkDocs page', $errors);
    assertNavigationContains("release-notes/{$releaseName}", $mintlifyNavigation, 'Mintlify', $errors);
    assertNavigationContains("release-notes/{$releaseName}.md", $mkdocsNavigation, 'MkDocs', $errors);
}

if ($errors !== []) {
    fwrite(STDERR, "Release-note parity validation failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

$suffix = $requestedRelease === '' ? '' : " for {$requestedRelease}";
fwrite(STDOUT, sprintf("Release-note parity passed%s (%d releases).\n", $suffix, count($releaseNames)));

function normalizeRelease(string $release): string
{
    $trimmed = trim($release);
    return match (true) {
        $trimmed === '' => '',
        str_starts_with($trimmed, 'v') => $trimmed,
        default => 'v' . $trimmed,
    };
}

/** @param list<string> $errors */
function loadMintlifyNavigation(string $path, array &$errors): array
{
    if (!is_file($path)) {
        $errors[] = "Mintlify configuration is missing: {$path}";
        return [];
    }

    $content = file_get_contents($path);
    $config = is_string($content) ? json_decode($content, true) : null;
    if (!is_array($config)) {
        $errors[] = "Mintlify configuration is invalid JSON: {$path}";
        return [];
    }

    return collectStrings($config['navigation'] ?? []);
}

/** @param list<string> $errors */
function loadMkDocsNavigation(string $path, array &$errors): array
{
    if (!is_file($path)) {
        $errors[] = "MkDocs configuration is missing: {$path}";
        return [];
    }

    try {
        $config = Yaml::parseFile($path);
    } catch (Throwable $throwable) {
        $errors[] = "MkDocs configuration is invalid YAML: {$throwable->getMessage()}";
        return [];
    }

    if (!is_array($config)) {
        $errors[] = "MkDocs configuration is not a mapping: {$path}";
        return [];
    }

    return collectStrings($config['nav'] ?? []);
}

/** @return list<string> */
function collectStrings(mixed $value): array
{
    if (is_string($value)) {
        return [$value];
    }

    if (!is_array($value)) {
        return [];
    }

    $strings = [];
    foreach ($value as $item) {
        array_push($strings, ...collectStrings($item));
    }
    return $strings;
}

/** @param list<string> $errors */
function assertFileExists(string $path, string $label, array &$errors): void
{
    if (!is_file($path)) {
        $errors[] = "{$label} is missing: {$path}";
    }
}

/** @param list<string> $navigation @param list<string> $errors */
function assertNavigationContains(string $entry, array $navigation, string $target, array &$errors): void
{
    if (!in_array($entry, $navigation, true)) {
        $errors[] = "{$target} navigation is missing: {$entry}";
    }
}
