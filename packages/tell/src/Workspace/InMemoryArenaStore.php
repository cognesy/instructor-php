<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalRecord;
use Cognesy\Tell\Canonical\CanonicalSerializer;

/** Real process-local canonical arena with the same hash and CAS invariants. */
final class InMemoryArenaStore implements CanUseTellArena
{
    /** @var array<string, string> canonical encoded bytes by hash */
    private array $objects = [];

    /** @var array<string, ArenaRef> */
    private array $refs;

    private CurrentBranchSelector $current;

    public function __construct(private readonly CanonicalSerializer $serializer = new CanonicalSerializer)
    {
        $this->refs = ['main' => ArenaRef::empty()];
        $this->current = CurrentBranchSelector::main();
    }

    public function put(CanonicalRecord $record): CanonicalHash
    {
        $bytes = $this->serializer->encode($record);
        $hash = CanonicalHash::fromBytes($bytes);
        $key = $hash->toString();
        if (isset($this->objects[$key]) && $this->objects[$key] !== $bytes) {
            throw new ArenaIntegrityException('Canonical hash collision in memory arena.');
        }
        $this->objects[$key] = $bytes;
        return $hash;
    }

    public function exists(CanonicalHash $hash): bool
    {
        return isset($this->objects[$hash->toString()]);
    }

    public function get(CanonicalHash $hash): CanonicalRecord
    {
        $bytes = $this->objects[$hash->toString()] ?? null;
        if ($bytes === null) {
            throw new ArenaIntegrityException("Tell object does not exist: {$hash}");
        }
        if (! CanonicalHash::fromBytes($bytes)->equals($hash)) {
            throw new ArenaIntegrityException('Tell in-memory object hash does not match its content.');
        }
        return $this->serializer->decode($bytes);
    }

    public function readRef(string $ref = 'main'): ArenaRef
    {
        return $this->refs[$this->ref($ref)] ?? ArenaRef::empty();
    }

    public function readOptionalRef(string $ref): ?ArenaRef
    {
        return $this->refs[$this->ref($ref)] ?? null;
    }

    public function createBranch(BranchName $name, ArenaRef $reference): ArenaRef
    {
        if ($reference->head !== null) {
            $this->get($reference->head);
        }
        $ref = 'branches/'.$name->toString();
        if (isset($this->refs[$ref])) {
            throw new ArenaRefConflict($ref, null, $this->refs[$ref]->head);
        }
        return $this->refs[$ref] = $reference;
    }

    public function readCurrentBranch(): CurrentBranchSelector
    {
        return $this->current;
    }

    public function checkout(string $branch): CurrentBranchSelector
    {
        $branch = $branch === 'main' ? 'main' : BranchName::from($branch)->toString();
        $ref = $branch === 'main' ? 'main' : 'branches/'.$branch;
        if (! isset($this->refs[$ref])) {
            throw new ArenaException("Tell branch '{$branch}' does not exist.");
        }
        return $this->current = new CurrentBranchSelector($branch);
    }

    public function compareAndSwap(string $ref, ?CanonicalHash $expectedHead, CanonicalHash $newHead): ArenaRef
    {
        $ref = $this->ref($ref);
        $this->get($newHead);
        $current = $this->refs[$ref] ?? ArenaRef::empty();
        if (! $this->same($current->head, $expectedHead)) {
            throw new ArenaRefConflict($ref, $expectedHead, $current->head);
        }
        return $this->refs[$ref] = new ArenaRef($newHead, $current->provenance);
    }

    public function compareAndSwapToEmpty(string $ref, ?CanonicalHash $expectedHead): ArenaRef
    {
        $ref = $this->ref($ref);
        $current = $this->refs[$ref] ?? ArenaRef::empty();
        if (! $this->same($current->head, $expectedHead)) {
            throw new ArenaRefConflict($ref, $expectedHead, $current->head);
        }
        if ($current->head === null) {
            return $current;
        }
        return $this->refs[$ref] = ArenaRef::empty();
    }

    private function same(?CanonicalHash $left, ?CanonicalHash $right): bool
    {
        return $left === null ? $right === null : ($right !== null && $left->equals($right));
    }

    private function ref(string $ref): string
    {
        if (
            preg_match('/\A[a-z][a-z0-9-]{0,63}\z/', $ref) !== 1
            && preg_match('/\Asessions\/[a-f0-9]{64}\z/', $ref) !== 1
            && ! (str_starts_with($ref, 'branches/') && $this->validBranch(substr($ref, 9)))
        ) {
            throw new ArenaException('Tell ref name is invalid.');
        }
        return $ref;
    }

    private function validBranch(string $name): bool
    {
        try {
            BranchName::fromStored($name);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
