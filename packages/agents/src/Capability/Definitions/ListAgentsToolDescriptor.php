<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ListAgentsToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: ListAgentsTool::TOOL_NAME,
            description: 'List known agent definitions with their descriptions.',
            metadata: [
                'name' => ListAgentsTool::TOOL_NAME,
                'summary' => 'List known agent definitions.',
                'namespace' => 'agent-definitions',
                'tags' => ['agents', 'definitions', 'list', 'read'],
            ],
            instructions: [
                'parameters' => [],
                'returns' => 'Known agent names and descriptions.',
            ],
        );
    }
}
