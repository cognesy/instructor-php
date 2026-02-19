<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrospective;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ExecutionRetrospectiveToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: ExecutionRetrospectiveTool::TOOL_NAME,
            description: <<<'DESC'
Send a message to your past self, rewinding the conversation to an earlier checkpoint.

You can see `CHECKPOINT {checkpoint_id}` messages in the conversation. When you realize
you have been making avoidable mistakes or going in circles, select one of those checkpoint
IDs as the destination.

After sending, the conversation will be rewound to the state BEFORE the selected checkpoint.
You will no longer see any messages after that checkpoint. Your guidance message will be
appended so your past self can take a better approach.

IMPORTANT: Side effects from previous actions (file changes, API calls) are NOT undone.
Your guidance should account for any environmental changes already made.

Typical scenarios:
- You read a file, found it very large and most content is irrelevant
- You searched with wrong parameters and wasted several steps
- You tried an approach that failed and now know the correct one
- You made repeated tool calls with wrong arguments before figuring out the right ones
DESC,
            metadata: [
                'name' => ExecutionRetrospectiveTool::TOOL_NAME,
                'summary' => 'Rewind conversation to a checkpoint with guidance for your past self.',
                'namespace' => 'retrospective',
                'tags' => ['retrospective', 'rewind', 'checkpoint'],
            ],
            instructions: [
                'parameters' => [
                    'checkpoint_id' => 'The checkpoint ID to rewind to (visible as CHECKPOINT N in conversation).',
                    'guidance' => 'Message to your past self explaining what you learned and how to proceed.',
                ],
                'returns' => 'Confirmation of rewind parameters, or error message if invalid.',
            ],
        );
    }
}
