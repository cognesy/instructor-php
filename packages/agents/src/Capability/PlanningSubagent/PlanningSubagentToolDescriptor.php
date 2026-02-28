<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\PlanningSubagent;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class PlanningSubagentToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: PlanningSubagentTool::TOOL_NAME,
            description: 'Generate an implementation plan using an isolated planning subagent.',
            metadata: [
                'name' => PlanningSubagentTool::TOOL_NAME,
                'summary' => 'Plan complex work before execution.',
                'namespace' => 'planning',
                'tags' => ['planning', 'subagent', 'orchestration'],
            ],
            instructions: [
                'parameters' => [
                    'specification' => 'Task specification text with sections like goal, context, expected outcomes, and acceptance criteria.',
                ],
                'returns' => 'Dense markdown implementation plan string.',
            ],
        );
    }
}
