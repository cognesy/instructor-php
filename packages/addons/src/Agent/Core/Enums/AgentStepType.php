<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
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