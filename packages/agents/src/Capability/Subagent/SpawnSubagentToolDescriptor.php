<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class SpawnSubagentToolDescriptor extends ToolDescriptor
{
    public function __construct(CanManageAgentDefinitions $provider) {
        parent::__construct(
            name: 'spawn_subagent',
            description: $this->buildDescription($provider),
            metadata: [
                'name' => 'spawn_subagent',
                'summary' => 'Spawn a specialized subagent for focused delegated tasks.',
                'namespace' => 'subagent',
                'tags' => ['subagent', 'delegation', 'orchestration'],
            ],
            instructions: [
                'parameters' => [
                    'subagent' => 'Registered subagent name.',
                    'prompt' => 'Task prompt for the subagent.',
                ],
                'returns' => 'Final state of executed subagent.',
            ],
        );
    }

    private function buildDescription(CanManageAgentDefinitions $provider): string {
        $count = $provider->count();

        return match (true) {
            $count === 0 => 'Spawn an isolated subagent for a focused task. No subagents are currently registered.',
            default => <<<DESC
Spawn a specialized subagent for a focused task. Returns only the final response.

Each subagent has specific expertise, tools, and capabilities optimized for its domain.
Choose the most appropriate subagent based on the task requirements.
DESC,
        };
    }
}
