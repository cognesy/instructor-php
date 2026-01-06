<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Enums;

enum AgentStepType : string
{
    case ToolExecution = 'tool_execution';
    case FinalResponse = 'final_response';
    case Error = 'error';

    public function is(?AgentStepType $type) : bool {
        return $this === $type;
    }
}