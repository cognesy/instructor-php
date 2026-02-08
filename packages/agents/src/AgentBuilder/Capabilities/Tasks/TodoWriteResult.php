<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

final readonly class TodoWriteResult
{
    /** @param array<int, array<string, mixed>> $tasks */
    public function __construct(
        public bool $success,
        public array $tasks,
        public string $rendered,
        public string $summary,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'tasks' => $this->tasks,
            'rendered' => $this->rendered,
            'summary' => $this->summary,
        ];
    }
}
