<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Hooks;

enum HookType: string
{
    case BeforeExecution = 'before_execution';
    case BeforeStep = 'before_step';
    case PreToolUse = 'pre_tool_use';
    case PostToolUse = 'post_tool_use';
    case AfterStep = 'after_step';
    case AfterExecution = 'after_execution';
    case OnError = 'on_error';
}
