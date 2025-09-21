<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Enums;

enum ToolUseStepType : string
{
    case ToolExecution = 'tool_execution';
    case FinalResponse = 'final_response';
    case Error = 'error';

    public function is(?ToolUseStepType $type) : bool {
        return $this === $type;
    }
}