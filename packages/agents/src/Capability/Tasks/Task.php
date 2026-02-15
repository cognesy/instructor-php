<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tasks;

use InvalidArgumentException;

final readonly class Task
{
    public function __construct(
        public string $content,
        public TaskStatus $status,
        public string $activeForm,
    ) {
        if ($this->content === '') {
            throw new InvalidArgumentException("'content' is required");
        }

        if ($this->activeForm === '') {
            throw new InvalidArgumentException("'activeForm' is required");
        }
    }

    public static function fromArray(array $data): self
    {
        $content = trim((string) ($data['content'] ?? ''));
        $status = (string) ($data['status'] ?? TaskStatus::Pending->value);
        $activeForm = trim((string) ($data['activeForm'] ?? $content));

        return new self(
            content: $content,
            status: TaskStatus::from($status),
            activeForm: $activeForm,
        );
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'status' => $this->status->value,
            'activeForm' => $this->activeForm,
        ];
    }

    public function withStatus(TaskStatus $status): self
    {
        return new self(
            content: $this->content,
            status: $status,
            activeForm: $this->activeForm,
        );
    }

    public function render(): string
    {
        $label = match ($this->status) {
            TaskStatus::Pending => '○',
            TaskStatus::InProgress => '◐',
            TaskStatus::Completed => '●',
        };

        $text = match ($this->status) {
            TaskStatus::InProgress => $this->activeForm,
            default => $this->content,
        };

        return $label . ' ' . $text;
    }
}
