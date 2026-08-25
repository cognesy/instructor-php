<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Coding;

use RuntimeException;

/**
 * A deliberately small unified-diff writer. It validates every hunk before
 * creating a temporary file, then commits each file atomically. Existing text
 * files only keeps the path and rollback contract explicit and bounded.
 */
final readonly class PatchOperation
{
    private const MAX_PATCH_BYTES = 262_144;
    private const MAX_SOURCE_BYTES = 2_097_152;

    private string $root;

    public function __construct(string $baseDir)
    {
        $root = realpath($baseDir);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException("Tell patch root does not exist: {$baseDir}");
        }
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    /** @return array<string, mixed> */
    public function applyUnified(string $patch): array
    {
        if ($patch === '' || strlen($patch) > self::MAX_PATCH_BYTES) {
            return $this->failure('invalid_patch_size', 'Patch must be between 1 byte and '.self::MAX_PATCH_BYTES.' bytes.');
        }

        try {
            $files = $this->parse($patch);
            $changes = [];
            foreach ($files as $file) {
                $source = $this->readSource($file['path']);
                $changes[] = [
                    'path' => $source['path'],
                    'relative' => $file['path'],
                    'source' => $source['content'],
                    'hash' => $source['hash'],
                    'content' => $this->applyHunks($source['content'], $file['hunks'], $file['path']),
                ];
            }

            return $this->commit($changes);
        } catch (PatchFailure $failure) {
            return $this->failure($failure->reason, $failure->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public function replace(string $path, string $old, string $new, bool $replaceAll): array
    {
        if ($old === '') {
            return $this->failure('empty_old_string', 'old_string cannot be empty.');
        }
        if ($old === $new) {
            return $this->failure('identical_replacement', 'old_string and new_string are identical.');
        }

        try {
            $source = $this->readSource($path);
            $occurrences = substr_count($source['content'], $old);
            if ($occurrences === 0) {
                throw new PatchFailure('hunk_failed', 'old_string was not found; no file was changed.');
            }
            if ($occurrences > 1 && !$replaceAll) {
                throw new PatchFailure('ambiguous_hunk', "old_string matched {$occurrences} times; use replace_all or add context.");
            }

            $content = $replaceAll
                ? str_replace($old, $new, $source['content'])
                : substr_replace($source['content'], $new, (int) strpos($source['content'], $old), strlen($old));

            return $this->commit([[
                'path' => $source['path'],
                'relative' => $path,
                'source' => $source['content'],
                'hash' => $source['hash'],
                'content' => $content,
            ]]);
        } catch (PatchFailure $failure) {
            return $this->failure($failure->reason, $failure->getMessage());
        }
    }

    /** @return list<array{path: string, hunks: list<array{oldStart: int, oldCount: int, newStart: int, newCount: int, lines: list<array{kind: string, value: string}>}>}> */
    private function parse(string $patch): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $patch));
        if (end($lines) === '') {
            array_pop($lines);
        }

        $index = 0;
        $files = [];
        while ($index < count($lines)) {
            if (!str_starts_with($lines[$index], '--- ')) {
                throw new PatchFailure('malformed_patch', 'Expected a --- file header.');
            }
            $oldPath = $this->headerPath(substr($lines[$index], 4));
            $index++;
            if (!isset($lines[$index]) || !str_starts_with($lines[$index], '+++ ')) {
                throw new PatchFailure('malformed_patch', 'Expected a +++ file header.');
            }
            $newPath = $this->headerPath(substr($lines[$index], 4));
            $index++;
            if ($oldPath !== $newPath) {
                throw new PatchFailure('unsupported_patch', 'Patch creation, deletion, and rename operations are not supported.');
            }
            if (isset($files[$oldPath])) {
                throw new PatchFailure('malformed_patch', "Patch contains {$oldPath} more than once.");
            }

            /** @var list<array{oldStart: int, oldCount: int, newStart: int, newCount: int, lines: list<array{kind: string, value: string}>}> $hunks */
            $hunks = [];
            while ($index < count($lines) && str_starts_with($lines[$index], '@@ ')) {
                /** @var array{oldStart: int, oldCount: int, newStart: int, newCount: int, lines: list<array{kind: string, value: string}>} $hunk */
                $hunk = $this->parseHunk($lines, $index);
                $hunks[] = $hunk;
            }
            if ($hunks === []) {
                throw new PatchFailure('malformed_patch', "Patch for {$oldPath} contains no hunks.");
            }
            $files[$oldPath] = ['path' => $oldPath, 'hunks' => $hunks];
        }

        return array_values($files);
    }

    private function headerPath(string $header): string
    {
        $path = trim(explode("\t", $header, 2)[0]);
        if (str_starts_with($path, 'a/') || str_starts_with($path, 'b/')) {
            $path = substr($path, 2);
        }
        if ($path === '' || $path === '/dev/null' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            throw new PatchFailure('path_denied', 'Patch path must be a relative path inside the Tell working directory.');
        }

        return $path;
    }

    /** @param list<string> $lines @return array{oldStart: int, oldCount: int, newStart: int, newCount: int, lines: list<array{kind: string, value: string}>} */
    private function parseHunk(array $lines, int &$index): array
    {
        $header = $lines[$index];
        if (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@(?: .*)?$/', $header, $matches) !== 1) {
            throw new PatchFailure('malformed_patch', "Invalid hunk header: {$header}");
        }
        $index++;
        /** @var list<array{kind: string, value: string}> $hunkLines */
        $hunkLines = [];
        while ($index < count($lines) && !str_starts_with($lines[$index], '@@ ') && !str_starts_with($lines[$index], '--- ')) {
            $line = $lines[$index];
            if ($line === '' || !in_array($line[0], [' ', '+', '-'], true)) {
                throw new PatchFailure('malformed_patch', 'Each hunk line must begin with a space, +, or -.');
            }
            $hunkLines[] = ['kind' => $line[0], 'value' => substr($line, 1)];
            $index++;
        }
        $oldCount = $matches[2] !== '' ? (int) $matches[2] : 1;
        $newCount = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 1;
        $actualOld = count(array_filter($hunkLines, static fn (array $line): bool => $line['kind'] !== '+'));
        $actualNew = count(array_filter($hunkLines, static fn (array $line): bool => $line['kind'] !== '-'));
        if ($actualOld !== $oldCount || $actualNew !== $newCount) {
            throw new PatchFailure('malformed_patch', 'Hunk line counts do not match its header.');
        }

        return [
            'oldStart' => (int) $matches[1],
            'oldCount' => $oldCount,
            'newStart' => (int) $matches[3],
            'newCount' => $newCount,
            'lines' => $hunkLines,
        ];
    }

    /** @return array{path: string, content: string, hash: string} */
    private function readSource(string $relative): array
    {
        $candidate = str_starts_with($relative, DIRECTORY_SEPARATOR)
            ? $relative
            : $this->root.DIRECTORY_SEPARATOR.$relative;
        if (is_link($candidate)) {
            throw new PatchFailure('path_denied', "Refusing symlink target: {$relative}");
        }
        $path = realpath($candidate);
        if ($path === false || !is_file($path) || !str_starts_with($path, $this->root.DIRECTORY_SEPARATOR)) {
            throw new PatchFailure('path_denied', "Path is not an existing regular file inside the Tell working directory: {$relative}");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new PatchFailure('read_failed', "Unable to read {$relative}.");
        }
        if (strlen($content) > self::MAX_SOURCE_BYTES) {
            throw new PatchFailure('source_too_large', "{$relative} exceeds the ".self::MAX_SOURCE_BYTES.'-byte patch source limit.');
        }

        return ['path' => $path, 'content' => $content, 'hash' => hash('sha256', $content)];
    }

    /** @param list<array{oldStart: int, oldCount: int, newStart: int, newCount: int, lines: list<array{kind: string, value: string}>}> $hunks */
    private function applyHunks(string $source, array $hunks, string $relative): string
    {
        $eol = str_contains($source, "\r\n") ? "\r\n" : "\n";
        $hasFinalNewline = str_ends_with($source, $eol);
        if (!$hasFinalNewline) {
            throw new PatchFailure('unsupported_patch', "{$relative} has no final newline; use the legacy edit alias for this file.");
        }
        $sourceLines = explode($eol, substr($source, 0, -strlen($eol)));
        $output = [];
        $cursor = 0;
        foreach ($hunks as $hunk) {
            $target = $hunk['oldStart'] - 1;
            if ($hunk['oldStart'] < 1 || $target < $cursor || $target > count($sourceLines)) {
                throw new PatchFailure('hunk_failed', "Hunk does not apply cleanly to {$relative}.");
            }
            array_push($output, ...array_slice($sourceLines, $cursor, $target - $cursor));
            $cursor = $target;
            foreach ($hunk['lines'] as $line) {
                if ($line['kind'] !== '+' && (!isset($sourceLines[$cursor]) || $sourceLines[$cursor] !== $line['value'])) {
                    throw new PatchFailure('hunk_failed', "Hunk context does not match {$relative}; no files were changed.");
                }
                if ($line['kind'] === ' ') {
                    $output[] = $sourceLines[$cursor++];
                } elseif ($line['kind'] === '-') {
                    $cursor++;
                } else {
                    $output[] = $line['value'];
                }
            }
        }
        array_push($output, ...array_slice($sourceLines, $cursor));

        return implode($eol, $output).$eol;
    }

    /** @param list<array{path: string, relative: string, source: string, hash: string, content: string}> $changes @return array<string, mixed> */
    private function commit(array $changes): array
    {
        $temporary = [];
        $backups = [];
        try {
            foreach ($changes as $change) {
                if (hash_file('sha256', $change['path']) !== $change['hash'] || is_link($change['path'])) {
                    throw new PatchFailure('source_changed', "{$change['relative']} changed while the patch was being prepared.");
                }
                $temp = tempnam(dirname($change['path']), '.tell-patch-');
                if ($temp === false) {
                    throw new PatchFailure('write_failed', "Could not prepare update for {$change['relative']}.");
                }
                $temporary[] = $temp;
                if (file_put_contents($temp, $change['content']) === false) {
                    throw new PatchFailure('write_failed', "Could not prepare update for {$change['relative']}.");
                }
                chmod($temp, fileperms($change['path']) & 0777);
                $backup = tempnam(dirname($change['path']), '.tell-patch-backup-');
                if ($backup === false || !copy($change['path'], $backup)) {
                    throw new PatchFailure('write_failed', "Could not protect {$change['relative']} before update.");
                }
                $backups[] = $backup;
            }
            foreach ($changes as $index => $pending) {
                if (!rename($temporary[$index], $pending['path'])) {
                    throw new PatchFailure('commit_failed', "Could not atomically update {$pending['relative']}.");
                }
                $temporary[$index] = '';
            }
        } catch (PatchFailure $failure) {
            $partial = false;
            foreach ($changes as $index => $change) {
                if (($temporary[$index] ?? null) === '' && isset($backups[$index]) && !rename($backups[$index], $change['path'])) {
                    $partial = true;
                }
            }
            $this->cleanup(array_values($temporary), $backups);
            return $this->failure($failure->reason, $failure->getMessage(), $partial);
        }
        $this->cleanup([], $backups);

        return [
            'success' => true,
            'operation' => 'apply_patch',
            'data' => [
                'files' => array_map(static fn (array $change): string => $change['relative'], $changes),
                'changed_files' => count($changes),
            ],
            'error' => null,
            'truncated' => false,
            'partial' => false,
        ];
    }

    /** @param list<string> $temporary @param list<string> $backups */
    private function cleanup(array $temporary, array $backups): void
    {
        foreach (array_merge($temporary, $backups) as $path) {
            if ($path !== '' && is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @return array<string, mixed> */
    private function failure(string $code, string $message, bool $partial = false): array
    {
        return [
            'success' => false,
            'operation' => 'apply_patch',
            'data' => [],
            'error' => ['code' => $code, 'message' => $message],
            'truncated' => false,
            'partial' => $partial,
        ];
    }
}

final class PatchFailure extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
