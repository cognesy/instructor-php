<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalRecord;

/** Internal canonical-store seam kept inside the cohesive workspace module. */
interface CanUseTellArena
{
    public function put(CanonicalRecord $record): CanonicalHash;
    public function exists(CanonicalHash $hash): bool;
    public function get(CanonicalHash $hash): CanonicalRecord;
    public function readRef(string $ref = 'main'): ArenaRef;
    public function readOptionalRef(string $ref): ?ArenaRef;
    public function createBranch(BranchName $name, ArenaRef $reference): ArenaRef;
    public function readCurrentBranch(): CurrentBranchSelector;
    public function checkout(string $branch): CurrentBranchSelector;
    public function compareAndSwap(string $ref, ?CanonicalHash $expectedHead, CanonicalHash $newHead): ArenaRef;
    public function compareAndSwapToEmpty(string $ref, ?CanonicalHash $expectedHead): ArenaRef;
}
