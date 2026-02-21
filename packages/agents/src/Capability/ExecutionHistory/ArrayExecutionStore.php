<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

use Cognesy\Agents\Data\AgentId;

/**
 * In-memory execution store backed by a plain array.
 * Useful for testing, short-lived scripts, and single-process agents.
 */
final class ArrayExecutionStore implements ExecutionStore
{
    /** @var array<string, ExecutionSummary[]> */
    private array $store = [];

    #[\Override]
    public function record(AgentId $agentId, ExecutionSummary $summary): void
    {
        $this->store[$agentId->toString()][] = $summary;
    }

    #[\Override]
    public function all(AgentId $agentId): array
    {
        return $this->store[$agentId->toString()] ?? [];
    }

    #[\Override]
    public function last(AgentId $agentId): ?ExecutionSummary
    {
        $history = $this->store[$agentId->toString()] ?? [];
        return $history !== [] ? $history[array_key_last($history)] : null;
    }

    #[\Override]
    public function count(AgentId $agentId): int
    {
        return count($this->store[$agentId->toString()] ?? []);
    }
}
