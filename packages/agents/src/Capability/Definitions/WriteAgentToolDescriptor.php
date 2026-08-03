<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class WriteAgentToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: WriteAgentTool::TOOL_NAME,
            description: 'Validate and persist an agent definition inside the configured writable root.',
            metadata: [
                'name' => WriteAgentTool::TOOL_NAME,
                'summary' => 'Safely persist an agent definition.',
                'namespace' => 'agent-definitions',
                'tags' => ['agents', 'definitions', 'write'],
            ],
            instructions: [
                'parameters' => [
                    'definition' => 'Complete agent definition object; its validated name determines the file.',
                    'overwrite' => 'Set true to replace an existing file.',
                ],
                'returns' => 'Validation problems or created/replaced persistence status.',
            ],
        );
    }
}
