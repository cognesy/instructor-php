<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ResearchSubagentToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'research_subagent',
            description: 'Spawn a subagent to research files and return a summary. Use for reading and analyzing file contents.',
            metadata: [
                'name' => 'research_subagent',
                'summary' => 'Delegate targeted file research to a temporary subagent.',
                'namespace' => 'subagent',
                'tags' => ['subagent', 'research', 'files'],
            ],
            instructions: [
                'parameters' => [
                    'task' => 'Research task for the subagent.',
                    'files' => 'Optional list of relevant file paths.',
                ],
                'returns' => 'Concise research summary from subagent execution.',
            ],
        );
    }
}
