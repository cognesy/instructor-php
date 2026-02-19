<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

/**
 * Contract for storing and retrieving execution summaries by agent ID.
 */
interface ExecutionStore
{
    public function record(string $agentId, ExecutionSummary $summary): void;

    /** @return ExecutionSummary[] */
    public function all(string $agentId): array;

    public function last(string $agentId): ?ExecutionSummary;

    public function count(string $agentId): int;
}
