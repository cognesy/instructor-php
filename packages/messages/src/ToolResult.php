<?php declare(strict_types=1);

namespace Cognesy\Messages;

use InvalidArgumentException;

final readonly class ToolResult
{
    private ?ToolCallId $callId;

    public function __construct(
        private string $content,
        ToolCallId|string|null $callId = null,
        private ?string $toolName = null,
        private bool $isError = false,
    ) {
        $this->callId = self::toToolCallId($callId);
    }

    public static function success(string $content, string|ToolCallId|null $callId = null, ?string $toolName = null): self
    {
        return new self($content, $callId, $toolName, false);
    }

    public static function error(string $content, string|ToolCallId|null $callId = null, ?string $toolName = null): self
    {
        return new self($content, $callId, $toolName, true);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            content: self::contentFrom($data),
            callId: $data['call_id'] ?? null,
            toolName: self::nullableString($data['tool_name'] ?? null),
            isError: (bool) ($data['is_error'] ?? false),
        );
    }

    public function content(): string
    {
        return $this->content;
    }

    public function callId(): ?ToolCallId
    {
        return $this->callId;
    }

    public function callIdString(): string
    {
        return $this->callId?->toString() ?? '';
    }

    public function toolName(): ?string
    {
        return $this->toolName;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'call_id' => $this->callId?->toNullableString(),
            'tool_name' => $this->toolName,
            'is_error' => $this->isError,
        ];
    }

    private static function contentFrom(array $data): string
    {
        $content = $data['content'] ?? '';

        return match (true) {
            is_string($content) => $content,
            default => throw new InvalidArgumentException('ToolResult content must be a string.'),
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) && $value !== '' => $value,
            default => null,
        };
    }

    private static function toToolCallId(string|ToolCallId|null $value): ?ToolCallId
    {
        return match (true) {
            $value === null => null,
            $value instanceof ToolCallId => $value,
            is_string($value) && $value !== '' => new ToolCallId($value),
            default => null,
        };
    }
}
