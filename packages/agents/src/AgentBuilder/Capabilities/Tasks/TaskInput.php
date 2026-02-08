<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tasks;

use InvalidArgumentException;

/**
 * Value object representing raw task input from tool invocation.
 * Handles validation and parsing of untyped LLM tool call data.
 */
final readonly class TaskInput
{
    public function __construct(
        public string $content,
        public string $status,
        public string $activeForm,
    ) {}

    /**
     * Parse and validate a single task input from array data.
     *
     * @param array<string, mixed> $data Raw input data
     * @param int $position 1-based position for error messages
     * @throws InvalidArgumentException if validation fails
     */
    public static function fromArray(array $data, int $position = 1): self {
        if (!isset($data['content']) || !is_string($data['content']) || trim($data['content']) === '') {
            throw new InvalidArgumentException(
                "Task #{$position}: 'content' is required and cannot be empty"
            );
        }

        if (!isset($data['status']) || !is_string($data['status'])) {
            throw new InvalidArgumentException(
                "Task #{$position}: 'status' is required (pending/in_progress/completed)"
            );
        }

        $validStatuses = ['pending', 'in_progress', 'completed'];
        if (!in_array($data['status'], $validStatuses, true)) {
            throw new InvalidArgumentException(
                "Task #{$position}: 'status' must be one of: " . implode(', ', $validStatuses)
            );
        }

        if (!isset($data['activeForm']) || !is_string($data['activeForm']) || trim($data['activeForm']) === '') {
            throw new InvalidArgumentException(
                "Task #{$position}: 'activeForm' is required (present tense action, e.g., 'Running tests')"
            );
        }

        return new self(
            content: trim($data['content']),
            status: $data['status'],
            activeForm: trim($data['activeForm']),
        );
    }

    /**
     * Parse multiple task inputs from mixed data.
     *
     * @param mixed $data Raw input (expected to be array of task arrays)
     * @return list<self>
     * @throws InvalidArgumentException if validation fails
     */
    public static function listFromMixed(mixed $data): array {
        if (!is_array($data)) {
            return [];
        }

        $inputs = [];
        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(
                    "Task #" . ($index + 1) . ": expected object, got " . gettype($item)
                );
            }
            $inputs[] = self::fromArray($item, $index + 1);
        }

        return $inputs;
    }

    /**
     * Convert to Task domain object.
     */
    public function toTask(): Task {
        return new Task(
            content: $this->content,
            status: TaskStatus::from($this->status),
            activeForm: $this->activeForm,
        );
    }
}
