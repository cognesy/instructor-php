<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\ContentType;
use Cognesy\Messages\ToolCallId;

/**
 * One ordered, provider-neutral change to an assistant message block.
 */
final readonly class AssistantMessageChunk
{
    private function __construct(
        public AssistantMessageChunkType $type,
        public string $index,
        public string $blockType = '',
        public string $text = '',
        public ToolCallId|string|null $toolCallId = null,
        public string $toolCallName = '',
        public string $toolCallArguments = '',
        public ?ContentPart $block = null,
    ) {}

    public static function blockStart(int|string $index, ContentType|string $blockType): self
    {
        return new self(
            type: AssistantMessageChunkType::BlockStart,
            index: (string) $index,
            blockType: self::normalizeBlockType($blockType),
        );
    }

    public static function textDelta(int|string $index, string $text): self
    {
        return new self(
            type: AssistantMessageChunkType::TextDelta,
            index: (string) $index,
            blockType: ContentType::Text->value,
            text: $text,
        );
    }

    public static function reasoningDelta(int|string $index, string $text): self
    {
        return new self(
            type: AssistantMessageChunkType::ReasoningDelta,
            index: (string) $index,
            blockType: ContentType::Reasoning->value,
            text: $text,
        );
    }

    public static function toolCallDelta(
        int|string $index,
        ToolCallId|string|null $id = null,
        string $name = '',
        string $arguments = '',
    ): self {
        return new self(
            type: AssistantMessageChunkType::ToolCallDelta,
            index: (string) $index,
            blockType: ContentType::ToolCall->value,
            toolCallId: $id,
            toolCallName: $name,
            toolCallArguments: $arguments,
        );
    }

    public static function blockEnd(int|string $index, ContentPart $block): self
    {
        return new self(
            type: AssistantMessageChunkType::BlockEnd,
            index: (string) $index,
            blockType: $block->type(),
            block: $block,
        );
    }

    private static function normalizeBlockType(ContentType|string $blockType): string
    {
        return match (true) {
            $blockType instanceof ContentType => $blockType->value,
            default => $blockType,
        };
    }
}
