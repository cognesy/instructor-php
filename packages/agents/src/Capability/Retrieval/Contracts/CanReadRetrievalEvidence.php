<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval\Contracts;

/**
 * Application-owned authorization boundary for retrieval reads.
 *
 * Implementations resolve only references permitted for the current execution,
 * tenant, and collection. Returning null means unavailable or unauthorized.
 */
interface CanReadRetrievalEvidence
{
    public function read(string $reference, int $maxBytes): ?string;
}
