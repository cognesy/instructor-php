<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Dto\StreamEvent;

/**
 * Message containing role and content items
 */
final readonly class Message
{
    /**
     * @param list<MessageContent> $content
     */
    public function __construct(
        public string $role,
        public array $content,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $contentData = $data['content'] ?? [];
        if (!is_array($contentData)) {
            $contentData = [];
        }

        $content = [];
        foreach ($contentData as $item) {
            if (is_array($item)) {
                $content[] = MessageContent::fromArray($item);
            }
        }

        return new self(
            role: $data['role'] ?? 'unknown',
            content: $content,
        );
    }

    /**
     * Get all text content items
     *
     * @return list<TextContent>
     */
    public function textContent(): array
    {
        return array_values(array_filter(
            $this->content,
            fn($item) => $item instanceof TextContent
        ));
    }

    /**
     * Get all tool use items
     *
     * @return list<ToolUseContent>
     */
    public function toolUses(): array
    {
        return array_values(array_filter(
            $this->content,
            fn($item) => $item instanceof ToolUseContent
        ));
    }

    /**
     * Get all tool result items
     *
     * @return list<ToolResultContent>
     */
    public function toolResults(): array
    {
        return array_values(array_filter(
            $this->content,
            fn($item) => $item instanceof ToolResultContent
        ));
    }
}
