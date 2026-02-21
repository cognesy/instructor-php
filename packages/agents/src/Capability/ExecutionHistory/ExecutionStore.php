<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

use Cognesy\Agents\Data\AgentId;

/**
 * Contract for storing and retrieving execution summaries by agent ID.
 */
interface ExecutionStore
{
    public function record(AgentId $agentId, ExecutionSummary $summary): void;

    /** @return ExecutionSummary[] */
    public function all(AgentId $agentId): array;

    public function last(AgentId $agentId): ?ExecutionSummary;

    public function count(AgentId $agentId): int;
}
