<?php declare(strict_types=1);

namespace Cognesy\Messages\Enums;

enum ContentType: string
{
    case Text = 'text';
    case Reasoning = 'reasoning';
    case Image = 'image_url';
    case File = 'file';
    case Audio = 'input_audio';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
}
