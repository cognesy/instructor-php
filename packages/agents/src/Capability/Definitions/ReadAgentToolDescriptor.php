<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ReadAgentToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: ReadAgentTool::TOOL_NAME,
            description: 'Read a known agent definition as canonical Markdown.',
            metadata: [
                'name' => ReadAgentTool::TOOL_NAME,
                'summary' => 'Read an agent definition.',
                'namespace' => 'agent-definitions',
                'tags' => ['agents', 'definitions', 'read'],
            ],
            instructions: [
                'parameters' => ['name' => 'Registered agent name.'],
                'returns' => 'Canonical Markdown definition source.',
            ],
        );
    }
}
