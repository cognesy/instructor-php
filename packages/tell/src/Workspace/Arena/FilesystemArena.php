<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Tell\Workspace\Arena\Exception\ArenaException;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaLockException;
use Cognesy\Tell\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Workspace\Arena\Record\StoredRecord;
use Cognesy\Tell\Workspace\Filesystem\PrivateFilesystem;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceRepository;
use Cognesy\Tell\Workspace\WorkspaceState;
use Override;
use Throwable;

/**
 * Immutable, content-addressed storage for a validated Tell workspace.
 *
 * Files are staged in their destination filesystem, flushed (and fsynced where
 * PHP exposes it), then renamed atomically. PHP has no portable parent-directory
 * fsync primitive, so directory-entry durability is delegated to the host filesystem.
 */
final class FilesystemArena implements CanUseArena
{
    private const int FANOUT_LENGTH = 2;
    private const int DEFAULT_LOCK_TIMEOUT_MILLISECONDS = 1_000;

    private readonly PrivateFilesystem $files;

    public function __construct(
        private readonly WorkspaceState $workspace,
        private readonly RecordCodec $serializer = new RecordCodec(),
        private readonly WorkspaceRepository $workspaces = new WorkspaceRepository(),
        private readonly int $lockTimeoutMilliseconds = self::DEFAULT_LOCK_TIMEOUT_MILLISECONDS,
        ?PrivateFilesystem $files = null,
    ) {
        if ($lockTimeoutMilliseconds < 1) {
            throw new ArenaException('Tell arena lock timeout must be positive.');
        }
        $this->files = $files ?? new PrivateFilesystem(
            integrityFailure: static fn (string $message, ?Throwable $previous): Throwable => new ArenaIntegrityException($message, previous: $previous),
            operationFailure: static fn (string $message, ?Throwable $previous): Throwable => new ArenaException($message, previous: $previous),
            lockFailure: static fn (string $message, ?Throwable $previous): Throwable => new ArenaLockException($message, previous: $previous),
        );
    }

    #[Override]
    public function put(StoredRecord $record): ObjectHash {
        $this->validateWorkspace();
        $bytes = $this->serializer->encode($record);
        $hash = ObjectHash::fromBytes($bytes);
        $this->verifyObjectBytes($hash, $bytes);

        $this->withLock('object-' . $hash->toString(), function () use ($hash, $bytes): void {
            $path = $this->objectPath($hash);
            if ($this->files->exists($path)) {
                $this->verifyObjectBytes($hash, $this->files->read($path, 'object'), $bytes);

                return;
            }
            $this->files->writeAtomically($path, $bytes, 'object', false);
            $this->verifyObjectBytes($hash, $this->files->read($path, 'object'), $bytes);
        });

        return $hash;
    }

    #[Override]
    public function exists(ObjectHash $hash): bool {
        $this->validateWorkspace();
        $path = $this->objectPath($hash);
        if (!$this->files->exists($path)) {
            return false;
        }

        $this->verifyObjectBytes($hash, $this->files->read($path, 'object'));

        return true;
    }

    #[Override]
    public function get(ObjectHash $hash): StoredRecord {
        $this->validateWorkspace();
        $path = $this->objectPath($hash);
        if (!$this->files->exists($path)) {
            throw new ArenaIntegrityException("Tell object does not exist: {$hash}");
        }

        return $this->verifyObjectBytes($hash, $this->files->read($path, 'object'));
    }

    #[Override]
    public function readRef(string $ref = 'main'): Ref {
        $this->validateWorkspace();

        return $this->readRefAtPath($this->refPath($ref));
    }

    #[Override]
    public function readOptionalRef(string $ref): ?Ref {
        $this->validateWorkspace();
        $path = $this->refPath($ref);
        if (!$this->files->exists($path)) {
            return null;
        }

        return $this->readRefAtPath($path);
    }

    /** @return list<string> */
    #[Override]
    public function refNames(string $prefix = ''): array {
        $this->validateWorkspace();
        $this->assertRefPrefix($prefix);
        $directory = $prefix === ''
            ? $this->workspace->paths->refs
            : $this->workspace->paths->refs . DIRECTORY_SEPARATOR . $prefix;
        if (!$this->files->exists($directory)) {
            return [];
        }
        if (is_link($directory) || !is_dir($directory)) {
            throw new ArenaIntegrityException('Tell ref directory is not safe.');
        }
        $entries = scandir($directory);
        if ($entries === false) {
            throw new ArenaIntegrityException('Tell refs could not be listed safely.');
        }

        $refs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }
            $ref = $prefix === '' ? $entry : $prefix . '/' . $entry;
            $this->refPath($ref);
            $refs[] = $ref;
        }
        sort($refs, SORT_STRING);

        return $refs;
    }

    #[Override]
    public function createRef(string $ref, Ref $reference): Ref {
        $this->validateWorkspace();
        if ($reference->head !== null) {
            $this->get($reference->head);
        }
        $path = $this->refPath($ref);

        return $this->withLock($this->refLockName($ref), function () use ($path, $ref, $reference): Ref {
            $this->validateWorkspace();
            if ($this->files->exists($path)) {
                throw new RefConflict($ref, null, $this->readRefAtPath($path)->head);
            }
            $this->files->writeAtomically($path, $reference->toBytes(), 'ref', false);

            return $this->readRefAtPath($path);
        });
    }

    #[Override]
    public function compareAndSwap(string $ref, ?ObjectHash $expectedHead, ObjectHash $newHead): Ref {
        $this->validateWorkspace();
        $this->get($newHead);
        $path = $this->refPath($ref);

        return $this->withLock($this->refLockName($ref), function () use ($expectedHead, $newHead, $path, $ref): Ref {
            $this->validateWorkspace();
            $current = $this->files->exists($path)
                ? $this->readRefAtPath($path)
                : Ref::empty();
            if (!$this->sameHash($current->head, $expectedHead)) {
                throw new RefConflict($ref, $expectedHead, $current->head);
            }

            $published = new Ref($newHead, $current->provenance);
            $this->files->writeAtomically($path, $published->toBytes(), 'ref', true);

            return $this->readRefAtPath($path);
        });
    }

    /**
     * Atomically empties a ref after the caller has validated its captured head.
     *
     * A missing optional ref and an existing empty ref are both idempotent no-ops,
     * so clearing a never-published named-session selector creates no ref.
     */
    #[Override]
    public function compareAndSwapToEmpty(string $ref, ?ObjectHash $expectedHead): Ref {
        $this->validateWorkspace();
        $path = $this->refPath($ref);

        return $this->withLock($this->refLockName($ref), function () use ($expectedHead, $path, $ref): Ref {
            $this->validateWorkspace();
            $current = $this->files->exists($path)
                ? $this->readRefAtPath($path)
                : Ref::empty();
            if (!$this->sameHash($current->head, $expectedHead)) {
                throw new RefConflict($ref, $expectedHead, $current->head);
            }
            if ($current->head === null) {
                return $current;
            }

            $this->files->writeAtomically($path, Ref::empty()->toBytes(), 'ref', true);

            return $this->readRefAtPath($path);
        });
    }

    public function objectPath(ObjectHash $hash): string {
        $value = $hash->toString();

        return $this->workspace->paths->objects
            . DIRECTORY_SEPARATOR . substr($value, 0, self::FANOUT_LENGTH)
            . DIRECTORY_SEPARATOR . substr($value, self::FANOUT_LENGTH);
    }

    private function refPath(string $ref): string {
        if (
            preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $ref) !== 1
            && preg_match('/\Asessions\/[a-f0-9]{64}\z/', $ref) !== 1
            && preg_match('/\Abranches\/[a-z][a-z0-9-]{0,63}\z/', $ref) !== 1
        ) {
            throw new ArenaException('Tell ref name is invalid.');
        }

        return $this->workspace->paths->refs . DIRECTORY_SEPARATOR . $ref;
    }

    private function assertRefPrefix(string $prefix): void {
        if (!in_array($prefix, ['', 'branches', 'sessions'], true)) {
            throw new ArenaException('Tell ref prefix is invalid.');
        }
    }

    private function refLockName(string $ref): string {
        return match (str_contains($ref, '/')) {
            false => 'ref-' . $ref,
            true => 'ref-' . hash('sha256', $ref),
        };
    }

    private function validateWorkspace(): void {
        try {
            $validated = $this->workspaces->validate($this->workspace);
        } catch (WorkspaceException $exception) {
            throw new ArenaIntegrityException('Tell workspace is no longer safe for arena access.', previous: $exception);
        }
        if ($validated->paths->root !== $this->workspace->paths->root) {
            throw new ArenaIntegrityException('Tell workspace changed while accessing the arena.');
        }
    }

    private function readRefAtPath(string $path): Ref {
        try {
            return Ref::fromBytes($this->files->read($path, 'ref'));
        } catch (ArenaException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ArenaIntegrityException('Tell ref could not be read safely.', previous: $exception);
        }
    }

    private function verifyObjectBytes(ObjectHash $hash, string $bytes, ?string $expectedBytes = null): StoredRecord {
        if (!$hash->equals(ObjectHash::fromBytes($bytes))) {
            throw new ArenaIntegrityException("Tell object bytes do not match {$hash}.");
        }
        if ($expectedBytes !== null && !hash_equals($expectedBytes, $bytes)) {
            throw new ArenaIntegrityException("Tell object {$hash} conflicts with different bytes.");
        }
        try {
            return $this->serializer->decode($bytes, $hash);
        } catch (RecordException $exception) {
            throw new ArenaIntegrityException("Tell object {$hash} is malformed.", previous: $exception);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(string $name, callable $callback): mixed {
        if (preg_match('/\A[a-z0-9-]{1,160}\z/', $name) !== 1) {
            throw new ArenaException('Tell arena lock name is invalid.');
        }

        return $this->files->withExclusiveLock(
            $this->workspace->paths->locks . DIRECTORY_SEPARATOR . $name . '.lock',
            "arena lock: {$name}",
            $callback,
            $this->lockTimeoutMilliseconds,
        );
    }

    private function sameHash(?ObjectHash $left, ?ObjectHash $right): bool {
        return match (true) {
            $left === null && $right === null => true,
            $left === null || $right === null => false,
            default => $left->equals($right),
        };
    }
}
