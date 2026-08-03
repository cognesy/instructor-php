<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Describe;

use Cognesy\Agents\Tool\Contracts\CanContributeToPrompt;
use Cognesy\Agents\Tool\ToolDescriptor;
use Override;

final readonly class DescribeSelfToolDescriptor extends ToolDescriptor implements CanContributeToPrompt
{
    public function __construct() {
        parent::__construct(
            name: DescribeSelfTool::TOOL_NAME,
            description: 'Describe this built agent, including its resolved tools, capabilities, hooks, and runtime.',
            metadata: [
                'name' => DescribeSelfTool::TOOL_NAME,
                'summary' => 'Inspect this agent’s resolved self-description.',
                'namespace' => 'agent',
                'tags' => ['agent', 'describe', 'introspection', 'read'],
            ],
            instructions: [
                'parameters' => [
                    'section' => 'Optional: tools, capabilities, hooks, or all.',
                ],
                'returns' => 'Credential-safe description of the built agent.',
            ],
        );
    }

    #[Override]
    public function promptSnippet(): ?string {
        return 'Inspect this agent’s resolved capabilities, tools, hooks, and runtime';
    }

    /** @return list<string> */
    #[Override]
    public function promptGuidelines(): array {
        return [];
    }
}
