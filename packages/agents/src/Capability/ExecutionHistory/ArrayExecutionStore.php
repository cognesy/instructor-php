<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\ExecutionHistory;

/**
 * In-memory execution store backed by a plain array.
 * Useful for testing, short-lived scripts, and single-process agents.
 */
final class ArrayExecutionStore implements ExecutionStore
{
    /** @var array<string, ExecutionSummary[]> */
    private array $store = [];

    #[\Override]
    public function record(string $agentId, ExecutionSummary $summary): void
    {
        $this->store[$agentId][] = $summary;
    }

    #[\Override]
    public function all(string $agentId): array
    {
        return $this->store[$agentId] ?? [];
    }

    #[\Override]
    public function last(string $agentId): ?ExecutionSummary
    {
        $history = $this->store[$agentId] ?? [];
        return $history !== [] ? $history[array_key_last($history)] : null;
    }

    #[\Override]
    public function count(string $agentId): int
    {
        return count($this->store[$agentId] ?? []);
    }
}
