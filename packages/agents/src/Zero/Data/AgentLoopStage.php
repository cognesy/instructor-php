<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Zero\Hooks\HookType;

enum AgentLoopStage : string
{
    case BeforeExecution = 'before_execution';
    case BeforeStep = 'before_step';
    case BeforeToolCall = 'before_tool_call';
    case AfterToolCall = 'after_tool_call';
    case AfterStep = 'after_step';
    case AfterExecution = 'after_execution';
    case OnError = 'on_error';

    public function toHookType() : HookType {
        return HookType::from($this->value);
    }
}