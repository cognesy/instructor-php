<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

enum AssistantMessageChunkType: string
{
    case BlockStart = 'block_start';
    case TextDelta = 'text_delta';
    case ReasoningDelta = 'reasoning_delta';
    case ToolCallDelta = 'tool_call_delta';
    case BlockEnd = 'block_end';
}
