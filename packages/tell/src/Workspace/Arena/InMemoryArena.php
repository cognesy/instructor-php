<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Tell\Workspace\Arena\Exception\ArenaException;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Workspace\Arena\Record\StoredRecord;
use Override;

/** Process-local Arena with the same record, hash, and CAS invariants. */
final class InMemoryArena implements CanUseArena
{
    /** @var array<string, string> stable encoded record bytes by hash */
    private array $objects = [];

    /** @var array<string, Ref> */
    private array $refs;

    public function __construct(private readonly RecordCodec $serializer = new RecordCodec()) {
        $this->refs = ['main' => Ref::empty()];
    }

    #[Override]
    public function put(StoredRecord $record): ObjectHash {
        $bytes = $this->serializer->encode($record);
        $hash = ObjectHash::fromBytes($bytes);
        $key = $hash->toString();
        if (isset($this->objects[$key]) && $this->objects[$key] !== $bytes) {
            throw new ArenaIntegrityException('Object hash collision in memory arena.');
        }
        $this->objects[$key] = $bytes;

        return $hash;
    }

    #[Override]
    public function exists(ObjectHash $hash): bool {
        return isset($this->objects[$hash->toString()]);
    }

    #[Override]
    public function get(ObjectHash $hash): StoredRecord {
        $bytes = $this->objects[$hash->toString()] ?? null;
        if ($bytes === null) {
            throw new ArenaIntegrityException("Tell object does not exist: {$hash}");
        }
        if (!ObjectHash::fromBytes($bytes)->equals($hash)) {
            throw new ArenaIntegrityException('Tell in-memory object hash does not match its content.');
        }

        return $this->serializer->decode($bytes);
    }

    #[Override]
    public function readRef(string $ref = 'main'): Ref {
        return $this->refs[$this->ref($ref)] ?? Ref::empty();
    }

    #[Override]
    public function readOptionalRef(string $ref): ?Ref {
        return $this->refs[$this->ref($ref)] ?? null;
    }

    /** @return list<string> */
    #[Override]
    public function refNames(string $prefix = ''): array {
        if (!in_array($prefix, ['', 'branches', 'sessions'], true)) {
            throw new ArenaException('Tell ref prefix is invalid.');
        }
        $refs = array_keys(array_filter(
            $this->refs,
            static fn (Ref $value, string $ref): bool => $prefix === ''
                ? !str_contains($ref, '/')
                : str_starts_with($ref, $prefix . '/'),
            ARRAY_FILTER_USE_BOTH,
        ));
        sort($refs, SORT_STRING);

        return $refs;
    }

    #[Override]
    public function createRef(string $ref, Ref $reference): Ref {
        $ref = $this->ref($ref);
        if ($reference->head !== null) {
            $this->get($reference->head);
        }
        if (isset($this->refs[$ref])) {
            throw new RefConflict($ref, null, $this->refs[$ref]->head);
        }

        return $this->refs[$ref] = $reference;
    }

    #[Override]
    public function compareAndSwap(string $ref, ?ObjectHash $expectedHead, ObjectHash $newHead): Ref {
        $ref = $this->ref($ref);
        $this->get($newHead);
        $current = $this->refs[$ref] ?? Ref::empty();
        if (!$this->same($current->head, $expectedHead)) {
            throw new RefConflict($ref, $expectedHead, $current->head);
        }

        return $this->refs[$ref] = new Ref($newHead, $current->provenance);
    }

    #[Override]
    public function compareAndSwapToEmpty(string $ref, ?ObjectHash $expectedHead): Ref {
        $ref = $this->ref($ref);
        $current = $this->refs[$ref] ?? Ref::empty();
        if (!$this->same($current->head, $expectedHead)) {
            throw new RefConflict($ref, $expectedHead, $current->head);
        }
        if ($current->head === null) {
            return $current;
        }

        return $this->refs[$ref] = Ref::empty();
    }

    private function same(?ObjectHash $left, ?ObjectHash $right): bool {
        return $left === null ? $right === null : ($right !== null && $left->equals($right));
    }

    private function ref(string $ref): string {
        if (
            preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $ref) !== 1
            && preg_match('/\Asessions\/[a-f0-9]{64}\z/', $ref) !== 1
            && preg_match('/\Abranches\/[a-z][a-z0-9-]{0,63}\z/', $ref) !== 1
        ) {
            throw new ArenaException('Tell ref name is invalid.');
        }

        return $ref;
    }
}
