<?php declare(strict_types=1);

namespace Cognesy\Messages\Enums;

enum MessageType : string
{
    case Text = 'text';
    case AssistantToolCalls = 'assistant_tool_calls';
    case ToolResult = 'tool_result';
}
