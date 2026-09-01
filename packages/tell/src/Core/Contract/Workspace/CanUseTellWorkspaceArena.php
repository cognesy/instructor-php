<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

use Cognesy\Tell\Core\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Core\Workspace\Arena\Record\StoredRecord;
use Cognesy\Tell\Core\Workspace\Arena\Ref;

/** Stable store seam for immutable workspace records and mutable refs. */
interface CanUseTellWorkspaceArena
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
