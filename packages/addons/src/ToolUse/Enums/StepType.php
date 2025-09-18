<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Enums;

enum StepType : string
{
    case ToolExecution = 'tool_execution';
    case FinalResponse = 'final_response';
    case Error = 'error';

    public function is(?StepType $type) : bool {
        return $this === $type;
    }
}