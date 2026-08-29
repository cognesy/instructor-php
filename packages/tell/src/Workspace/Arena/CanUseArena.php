<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

use Cognesy\Tell\Workspace\Arena\Record\StoredRecord;

/** Internal store seam for immutable Arena records and mutable refs. */
interface CanUseArena
{
    public function put(StoredRecord $record): ObjectHash;

    public function exists(ObjectHash $hash): bool;

    public function get(ObjectHash $hash): StoredRecord;

    public function readRef(string $ref = 'main'): Ref;

    public function readOptionalRef(string $ref): ?Ref;

    /** @return list<string> */
    public function refNames(string $prefix = ''): array;

    public function createRef(string $ref, Ref $reference): Ref;

    public function compareAndSwap(string $ref, ?ObjectHash $expectedHead, ObjectHash $newHead): Ref;

    public function compareAndSwapToEmpty(string $ref, ?ObjectHash $expectedHead): Ref;
}
