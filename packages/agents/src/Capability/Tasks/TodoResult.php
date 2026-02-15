<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tasks;

use JsonSerializable;

final readonly class TodoResult implements JsonSerializable
{
    /**
     * @param list<array{content: string, status: string, activeForm: string}> $tasks
     */
    public function __construct(
        public bool $success,
        public array $tasks,
        public string $summary,
        public string $rendered,
    ) {}

    #[\Override]
    public function jsonSerialize(): array {
        return $this->toArray();
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'tasks' => $this->tasks,
            'summary' => $this->summary,
            'rendered' => $this->rendered,
        ];
    }

    public function __toString(): string {
        if ($this->rendered === '') {
            return $this->summary;
        }
        if ($this->summary === '') {
            return $this->rendered;
        }
        return $this->rendered . "\n" . $this->summary;
    }
}
