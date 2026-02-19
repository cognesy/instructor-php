<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrospective;

final readonly class RetrospectivePolicy
{
    private const string DEFAULT_INSTRUCTIONS = <<<'PROMPT'
## Execution Retrospective

The conversation contains [CHECKPOINT N] markers before each step. You have access to
the `execution_retrospective` tool. If you realize you have been making avoidable mistakes,
searching with wrong patterns, reading irrelevant files, or going in circles — call
execution_retrospective with the checkpoint_id to rewind to and guidance explaining what
to do differently.

Your guidance will be delivered to your past self as a message from your future self.
The conversation will be rewound to the state before the selected checkpoint, removing
all messages after it. Side effects (file changes, API calls) are NOT undone — account
for them in your guidance.

Use this when you have wasted 2 or more steps on a wrong approach.
PROMPT;

    public function __construct(
        public int $maxRewinds = 3,
        public string $systemPromptInstructions = self::DEFAULT_INSTRUCTIONS,
    ) {}
}
