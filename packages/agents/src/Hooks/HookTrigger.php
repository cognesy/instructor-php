<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

enum HookTrigger : string
{
    case BeforeExecution = 'before_execution';
    case BeforeStep = 'before_step';
    case BeforeToolUse = 'before_tool_use';
    case AfterToolUse = 'after_tool_use';
    case AfterStep = 'after_step';
    case AfterExecution = 'after_execution';
    case OnError = 'on_error';

    public function equals(HookTrigger $type) : bool {
        return $this === $type;
    }
}
