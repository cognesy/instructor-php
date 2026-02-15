<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\SelfCritique;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class SelfCriticSubagentToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'self_critic',
            description: 'Evaluate if your response adequately addresses the original task. Use before finalizing to ensure quality. Returns approval status and improvement suggestions.',
            metadata: [
                'name' => 'self_critic',
                'summary' => 'Run a critic subagent to evaluate response quality.',
                'namespace' => 'self_critique',
                'tags' => ['critique', 'quality', 'evaluation'],
            ],
            instructions: [
                'parameters' => [
                    'original_task' => 'Original user task.',
                    'proposed_response' => 'Proposed response to evaluate.',
                ],
                'returns' => 'Evaluation text with explicit approval status.',
            ],
        );
    }
}
