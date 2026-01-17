<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Tasks;

class Task
{
    public function __construct(
        public string $content,
        public TaskStatus $status,
        public string $activeForm,
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            content: $data['content'] ?? '',
            status: TaskStatus::from($data['status'] ?? 'pending'),
            activeForm: $data['activeForm'] ?? $data['content'] ?? '',
        );
    }

    /** @return array{content: string, status: string, activeForm: string} */
    public function toArray(): array {
        return [
            'content' => $this->content,
            'status' => $this->status->value,
            'activeForm' => $this->activeForm,
        ];
    }

    public function withStatus(TaskStatus $status): self {
        return new self(
            content: $this->content,
            status: $status,
            activeForm: $this->activeForm,
        );
    }

    public function render(): string {
        $label = $this->status->label();
        $text = $this->status === TaskStatus::InProgress
            ? $this->activeForm
            : $this->content;

        return "{$label} {$text}";
    }
}
