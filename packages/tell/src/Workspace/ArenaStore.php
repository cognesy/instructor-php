<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalException;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalRecord;
use Cognesy\Tell\Canonical\CanonicalSerializer;
use Throwable;

/**
 * Immutable, content-addressed storage for a validated Tell workspace.
 *
 * Files are staged in their destination filesystem, flushed (and fsynced where
 * PHP exposes it), then renamed atomically. PHP has no portable parent-directory
 * fsync primitive, so directory-entry durability is delegated to the host filesystem.
 */
final class ArenaStore
{
    private const int FANOUT_LENGTH = 2;

    private const int DEFAULT_LOCK_TIMEOUT_MILLISECONDS = 1_000;

    public function __construct(
        private readonly TellWorkspace $workspace,
        private readonly CanonicalSerializer $serializer = new CanonicalSerializer,
        private readonly WorkspaceManager $workspaces = new WorkspaceManager,
        private readonly int $lockTimeoutMilliseconds = self::DEFAULT_LOCK_TIMEOUT_MILLISECONDS,
    ) {
        if ($lockTimeoutMilliseconds < 1) {
            throw new ArenaException('Tell arena lock timeout must be positive.');
        }
    }

    public function put(CanonicalRecord $record): CanonicalHash
    {
        $this->validateWorkspace();
        $bytes = $this->serializer->encode($record);
        $hash = CanonicalHash::fromBytes($bytes);
        $this->verifyObjectBytes($hash, $bytes);

        $this->withLock('object-'.$hash->toString(), function () use ($hash, $bytes): void {
            $path = $this->objectPath($hash);
            if ($this->pathExists($path)) {
                $this->verifyObjectBytes($hash, $this->readPrivateFile($path, 'object'), $bytes);

                return;
            }
            $this->ensurePrivateDirectory(dirname($path), 'object fan-out directory');
            $this->writeAtomically(dirname($path), $path, $bytes, 'object', false);
            $this->verifyObjectBytes($hash, $this->readPrivateFile($path, 'object'), $bytes);
        });

        return $hash;
    }

    public function exists(CanonicalHash $hash): bool
    {
        $this->validateWorkspace();
        $path = $this->objectPath($hash);
        if (! $this->pathExists($path)) {
            return false;
        }

        $this->verifyObjectBytes($hash, $this->readPrivateFile($path, 'object'));

        return true;
    }

    public function get(CanonicalHash $hash): CanonicalRecord
    {
        $this->validateWorkspace();
        $path = $this->objectPath($hash);
        if (! $this->pathExists($path)) {
            throw new ArenaIntegrityException("Tell object does not exist: {$hash}");
        }

        return $this->verifyObjectBytes($hash, $this->readPrivateFile($path, 'object'));
    }

    public function readRef(string $ref = 'main'): ArenaRef
    {
        $this->validateWorkspace();

        return $this->readRefAtPath($this->refPath($ref));
    }

    public function readOptionalRef(string $ref): ?ArenaRef
    {
        $this->validateWorkspace();
        $path = $this->refPath($ref);
        if (! $this->pathExists($path)) {
            return null;
        }

        return $this->readRefAtPath($path);
    }

    /** @return list<string> */
    public function sessionRefNames(): array
    {
        $this->validateWorkspace();
        $directory = $this->workspace->paths->refs.DIRECTORY_SEPARATOR.'sessions';
        if (! $this->pathExists($directory)) {
            return [];
        }
        if (is_link($directory) || ! is_dir($directory)) {
            throw new ArenaIntegrityException('Tell session ref directory is not safe.');
        }
        $entries = scandir($directory);
        if ($entries === false) {
            throw new ArenaIntegrityException('Tell session refs could not be listed safely.');
        }

        $refs = [];
        foreach ($entries as $entry) {
            if (preg_match('/\A[a-f0-9]{64}\z/', $entry) !== 1) {
                continue;
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (is_link($path) || ! is_file($path)) {
                throw new ArenaIntegrityException('Tell session ref is not a safe regular file.');
            }
            $refs[] = 'sessions/'.$entry;
        }
        sort($refs, SORT_STRING);

        return $refs;
    }

    /** @return list<BranchName> */
    public function branchNames(): array
    {
        $this->validateWorkspace();
        $directory = $this->workspace->paths->refs.DIRECTORY_SEPARATOR.'branches';
        if (! $this->pathExists($directory)) {
            return [];
        }
        if (is_link($directory) || ! is_dir($directory)) {
            throw new ArenaIntegrityException('Tell branch ref directory is not safe.');
        }
        $entries = scandir($directory);
        if ($entries === false) {
            throw new ArenaIntegrityException('Tell branch refs could not be listed safely.');
        }

        $branches = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            try {
                $name = BranchName::fromStored($entry);
            } catch (\InvalidArgumentException $exception) {
                throw new ArenaIntegrityException('Tell branch ref name is invalid.', previous: $exception);
            }
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            if (is_link($path) || ! is_file($path)) {
                throw new ArenaIntegrityException('Tell branch ref is not a safe regular file.');
            }
            $branches[] = $name;
        }
        usort($branches, static fn (BranchName $left, BranchName $right): int => $left->toString() <=> $right->toString());

        return $branches;
    }

    public function createBranch(BranchName $name, ArenaRef $reference): ArenaRef
    {
        $this->validateWorkspace();
        if ($reference->head !== null) {
            $this->get($reference->head);
        }
        $ref = 'branches/'.$name->toString();
        $path = $this->refPath($ref);

        return $this->withLock($this->refLockName($ref), function () use ($path, $ref, $reference): ArenaRef {
            $this->validateWorkspace();
            if ($this->pathExists($path)) {
                throw new ArenaRefConflict($ref, null, $this->readRefAtPath($path)->head);
            }
            $this->writeAtomically(dirname($path), $path, $reference->toBytes(), 'branch ref', false);

            return $this->readRefAtPath($path);
        });
    }

    public function readCurrentBranch(): CurrentBranchSelector
    {
        $this->validateWorkspace();

        if (! $this->pathExists($this->workspace->paths->currentBranch)) {
            return CurrentBranchSelector::main();
        }

        return $this->readCurrentBranchAtPath($this->workspace->paths->currentBranch);
    }

    public function checkout(string $branch): CurrentBranchSelector
    {
        $this->validateWorkspace();
        $branch = $branch === 'main' ? 'main' : BranchName::from($branch)->toString();
        $ref = $branch === 'main' ? 'main' : 'branches/'.$branch;
        $reference = $this->readOptionalRef($ref);
        if ($reference === null) {
            throw new ArenaException("Tell branch '{$branch}' does not exist.");
        }
        if ($reference->head !== null) {
            $this->get($reference->head);
        }

        return $this->withLock('current-branch', function () use ($branch): CurrentBranchSelector {
            $this->validateWorkspace();
            $this->writeAtomically(
                dirname($this->workspace->paths->currentBranch),
                $this->workspace->paths->currentBranch,
                (new CurrentBranchSelector($branch))->toBytes(),
                'current branch selector',
                true,
            );

            return $this->readCurrentBranchAtPath($this->workspace->paths->currentBranch);
        });
    }

    public function compareAndSwap(string $ref, ?CanonicalHash $expectedHead, CanonicalHash $newHead): ArenaRef
    {
        $this->validateWorkspace();
        $this->get($newHead);
        $path = $this->refPath($ref);

        return $this->withLock($this->refLockName($ref), function () use ($expectedHead, $newHead, $path, $ref): ArenaRef {
            $this->validateWorkspace();
            $current = $this->pathExists($path)
                ? $this->readRefAtPath($path)
                : ArenaRef::empty();
            if (! $this->sameHash($current->head, $expectedHead)) {
                throw new ArenaRefConflict($ref, $expectedHead, $current->head);
            }

            $published = new ArenaRef($newHead, $current->provenance);
            $this->writeAtomically(dirname($path), $path, $published->toBytes(), 'ref', true);

            return $this->readRefAtPath($path);
        });
    }

    /**
     * Atomically empties a ref after the caller has validated its captured head.
     *
     * A missing optional ref and an existing empty ref are both idempotent no-ops,
     * so clearing a never-published named compatibility selector creates no ref.
     */
    public function compareAndSwapToEmpty(string $ref, ?CanonicalHash $expectedHead): ArenaRef
    {
        $this->validateWorkspace();
        $path = $this->refPath($ref);

        return $this->withLock($this->refLockName($ref), function () use ($expectedHead, $path, $ref): ArenaRef {
            $this->validateWorkspace();
            $current = $this->pathExists($path)
                ? $this->readRefAtPath($path)
                : ArenaRef::empty();
            if (! $this->sameHash($current->head, $expectedHead)) {
                throw new ArenaRefConflict($ref, $expectedHead, $current->head);
            }
            if ($current->head === null) {
                return $current;
            }

            $this->writeAtomically(dirname($path), $path, ArenaRef::empty()->toBytes(), 'ref', true);

            return $this->readRefAtPath($path);
        });
    }

    public function objectPath(CanonicalHash $hash): string
    {
        $value = $hash->toString();

        return $this->workspace->paths->objects
            .DIRECTORY_SEPARATOR.substr($value, 0, self::FANOUT_LENGTH)
            .DIRECTORY_SEPARATOR.substr($value, self::FANOUT_LENGTH);
    }

    private function refPath(string $ref): string
    {
        if (
            preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $ref) !== 1
            && preg_match('/\Asessions\/[a-f0-9]{64}\z/', $ref) !== 1
            && ! $this->isBranchRef($ref)
        ) {
            throw new ArenaException('Tell ref name is invalid.');
        }

        return $this->workspace->paths->refs.DIRECTORY_SEPARATOR.$ref;
    }

    private function isBranchRef(string $ref): bool
    {
        if (! str_starts_with($ref, 'branches/')) {
            return false;
        }
        try {
            BranchName::fromStored(substr($ref, strlen('branches/')));
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    private function refLockName(string $ref): string
    {
        return match (str_contains($ref, '/')) {
            false => 'ref-'.$ref,
            true => 'ref-'.hash('sha256', $ref),
        };
    }

    private function validateWorkspace(): void
    {
        try {
            $validated = $this->workspaces->validate($this->workspace);
        } catch (WorkspaceException $exception) {
            throw new ArenaIntegrityException('Tell workspace is no longer safe for arena access.', previous: $exception);
        }
        if ($validated->paths->root !== $this->workspace->paths->root) {
            throw new ArenaIntegrityException('Tell workspace changed while accessing the arena.');
        }
    }

    private function readRefAtPath(string $path): ArenaRef
    {
        try {
            return ArenaRef::fromBytes($this->readPrivateFile($path, 'ref'));
        } catch (ArenaException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ArenaIntegrityException('Tell ref could not be read safely.', previous: $exception);
        }
    }

    private function readCurrentBranchAtPath(string $path): CurrentBranchSelector
    {
        try {
            return CurrentBranchSelector::fromBytes($this->readPrivateFile($path, 'current branch selector'));
        } catch (ArenaException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ArenaIntegrityException('Tell current branch selector could not be read safely.', previous: $exception);
        }
    }

    private function verifyObjectBytes(CanonicalHash $hash, string $bytes, ?string $expectedBytes = null): CanonicalRecord
    {
        if (! $hash->equals(CanonicalHash::fromBytes($bytes))) {
            throw new ArenaIntegrityException("Tell object bytes do not match {$hash}.");
        }
        if ($expectedBytes !== null && ! hash_equals($expectedBytes, $bytes)) {
            throw new ArenaIntegrityException("Tell object {$hash} conflicts with different bytes.");
        }
        try {
            return $this->serializer->decode($bytes, $hash);
        } catch (CanonicalException $exception) {
            throw new ArenaIntegrityException("Tell object {$hash} is malformed.", previous: $exception);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(string $name, callable $callback): mixed
    {
        if (preg_match('/\A[a-z0-9-]{1,160}\z/', $name) !== 1) {
            throw new ArenaException('Tell arena lock name is invalid.');
        }
        $path = $this->workspace->paths->locks.DIRECTORY_SEPARATOR.$name.'.lock';
        $handle = $this->openPrivateFile($path, 'c', 'lock');
        try {
            $deadline = hrtime(true) + ($this->lockTimeoutMilliseconds * 1_000_000);
            do {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    try {
                        return $callback();
                    } finally {
                        flock($handle, LOCK_UN);
                    }
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline);

            throw new ArenaLockException("Timed out acquiring Tell arena lock: {$name}");
        } finally {
            fclose($handle);
        }
    }

    private function writeAtomically(string $directory, string $target, string $bytes, string $label, bool $replace): void
    {
        $this->ensurePrivateDirectory($directory, "{$label} directory");
        if (is_link($target)) {
            throw new ArenaIntegrityException("Unsafe symlinked Tell {$label}: {$target}");
        }
        if (! $replace && $this->pathExists($target)) {
            throw new ArenaIntegrityException("Tell {$label} already exists: {$target}");
        }

        $temporary = $directory.DIRECTORY_SEPARATOR.'.'.$label.'.tmp.'.bin2hex(random_bytes(12));
        $handle = null;
        try {
            $handle = $this->openPrivateFile($temporary, 'x', "{$label} temporary file");
            $this->writeAll($handle, $bytes, $label);
            if (! fflush($handle)) {
                throw new ArenaException("Unable to flush Tell {$label} temporary file.");
            }
            if (function_exists('fsync') && ! fsync($handle)) {
                throw new ArenaException("Unable to sync Tell {$label} temporary file.");
            }
            fclose($handle);
            $handle = null;

            if (! @rename($temporary, $target)) {
                throw new ArenaException("Unable to atomically publish Tell {$label}.");
            }
            @chmod($target, 0600);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($this->pathExists($temporary) && ! is_link($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @param resource $handle */
    private function writeAll($handle, string $bytes, string $label): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new ArenaException("Unable to write Tell {$label}.");
            }
            $offset += $written;
        }
    }

    private function readPrivateFile(string $path, string $label): string
    {
        if (is_link($path)) {
            throw new ArenaIntegrityException("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (! is_file($path)) {
            throw new ArenaIntegrityException("Tell {$label} is not a regular file: {$path}");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ArenaIntegrityException("Unable to read Tell {$label}: {$path}");
        }

        return $contents;
    }

    private function ensurePrivateDirectory(string $path, string $label): void
    {
        if (is_link($path)) {
            throw new ArenaIntegrityException("Unsafe symlinked Tell {$label}: {$path}");
        }
        if (is_dir($path)) {
            return;
        }
        if (! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new ArenaException("Unable to create Tell {$label}: {$path}");
        }
        @chmod($path, 0700);
    }

    private function openPrivateFile(string $path, string $mode, string $label)
    {
        if (is_link($path)) {
            throw new ArenaIntegrityException("Unsafe symlinked Tell {$label}: {$path}");
        }
        $previousUmask = umask(0077);
        try {
            $handle = @fopen($path, $mode);
        } finally {
            umask($previousUmask);
        }
        if ($handle === false) {
            throw new ArenaException("Unable to open Tell {$label}: {$path}");
        }
        @chmod($path, 0600);
        $pathStat = @lstat($path);
        $handleStat = fstat($handle);
        if ($pathStat === false || $handleStat === false || $pathStat['ino'] !== $handleStat['ino'] || $pathStat['dev'] !== $handleStat['dev']) {
            fclose($handle);
            throw new ArenaIntegrityException("Tell {$label} changed while opening it: {$path}");
        }

        return $handle;
    }

    private function pathExists(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    private function sameHash(?CanonicalHash $left, ?CanonicalHash $right): bool
    {
        return match (true) {
            $left === null && $right === null => true,
            $left === null || $right === null => false,
            default => $left->equals($right),
        };
    }
}
