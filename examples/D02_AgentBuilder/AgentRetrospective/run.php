---
title: 'Agent Execution Retrospective (D-Mail)'
docname: 'agent_retrospective'
order: 8
id: 'f3a1'
---
## Overview

Execution retrospective lets an agent "rewind" its conversation to an earlier checkpoint
when it realizes it has been going in circles or took a wrong path. Inspired by kimi-cli's
D-Mail mechanism, this capability injects visible `[CHECKPOINT N]` markers before each step.
When the agent calls `execution_retrospective(checkpoint_id, guidance)`, the message context
is truncated to before that checkpoint and the guidance is injected as a message from the
agent's "future self".

Key properties:
- **Only the message buffer is rewound** — execution history (steps, token usage) is preserved
- **Side effects are NOT undone** — file changes, API calls remain; guidance should account for them
- **Checkpoint markers are visible to the LLM** — the agent can reference them by ID
- **`onRewind` callback** — extension point for user-defined self-improvement (logging, memory, prompt tuning)

This significantly reduces wasted steps by:
- Cutting dead-end exploration from the context window
- Providing focused guidance to the agent's "past self"
- Preserving full execution history for observability

Key concepts:
- `UseExecutionRetrospective`: Capability that adds checkpoint markers, rewind logic, and system prompt instructions
- `RetrospectivePolicy`: Configuration (maxRewinds, systemPromptInstructions)
- `onRewind`: User callback invoked on every rewind with the result and agent state
- `AgentConsoleLogger`: Shows checkpoint injection, tool calls, and step progression

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseContextConfig;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Capability\Retrospective\ExecutionRetrospectiveResult;
use Cognesy\Agents\Capability\Retrospective\RetrospectivePolicy;
use Cognesy\Agents\Capability\Retrospective\UseExecutionRetrospective;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentConsoleLogger;
use Cognesy\Messages\Messages;

// Track rewinds for observability
$rewindLog = [];

// Create console logger for execution visibility
$logger = new AgentConsoleLogger(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Configure working directory — point at the Instructor codebase root (so `bd` works)
$workDir = dirname(__DIR__, 3);

// Build agent with bash + retrospective capabilities
// Note: The system prompt gives NO instructions about `bd` — the agent must explore it.
// The massive --help output becomes wasted context once the agent knows the right command.
// UseExecutionRetrospective automatically appends retrospective instructions
// to the system prompt via BeforeExecution hook — no manual prompt setup needed.
$agent = AgentBuilder::base()
    ->withCapability(new UseLLMConfig(model: 'gpt-5.2'))
    ->withCapability(new UseContextConfig(
        systemPrompt: <<<'SYSTEM'
        You are a CLI automation agent. You accomplish tasks using bash commands.
        Always limit command output — use --limit, | head -20, etc.

        WORKFLOW — you always work in two passes:
        Pass 1: Explore the tool (--help, trial runs). Once you get the result, do NOT answer.
                Instead call execution_retrospective to rewind with the exact command as guidance.
        Pass 2: After rewind, guidance from your future self is in the conversation.
                Trust it. Run the command from guidance. Answer. Done.
                Do NOT explore again. Do NOT call execution_retrospective again.
        SYSTEM,
    ))
    ->withCapability(new UseBash(baseDir: $workDir))
    ->withCapability(new UseExecutionRetrospective(
        policy: new RetrospectivePolicy(
            maxRewinds: 1,
            systemPromptInstructions: <<<'PROMPT'
            ## Execution Retrospective (IMPORTANT)

            The conversation contains [CHECKPOINT N] markers before each step. You have the
            `execution_retrospective` tool available.

            [CHECKPOINT N] markers appear before each step. You have `execution_retrospective`.

            After a rewind, guidance from your future self appears as an assistant message.
            If you see such guidance: trust it, run the command it specifies, answer. Done.
            Do NOT read --help. Do NOT explore. Do NOT call execution_retrospective again.
            PROMPT,
        ),
        onRewind: function (ExecutionRetrospectiveResult $result, AgentState $state) use (&$rewindLog) {
            $rewindLog[] = [
                'checkpoint' => $result->checkpointId,
                'guidance' => $result->guidance,
                'step' => $state->stepCount(),
            ];
            echo "\n  ** REWIND to checkpoint {$result->checkpointId}: {$result->guidance}\n\n";
        },
    ))
    ->withCapability(new UseGuards(maxSteps: 20, maxTokens: 65536, maxExecutionTime: 180))
    ->build()
    ->wiretap($logger->wiretap());

// Task: List issues using the `bd` CLI — with zero prior knowledge.
// The agent has no idea what `bd` is. It must explore via --help and trial/error.
//
// Expected flow:
// Phase 1 (steps 1-3): Agent explores `bd` (--help, list --help, maybe a wrong attempt)
//   → Context now polluted with massive help output
// Phase 2 (step 4): Agent successfully runs `bd list`
// Phase 3 (step 5): Agent recognizes exploration waste → calls execution_retrospective
//   → Rewinds to checkpoint 1 with guidance: "Run `bd list` to list issues"
// Phase 4 (step 6): With clean context, agent one-shots `bd list` and responds
// ~6 steps total, but context is clean after rewind
$question = <<<'QUESTION'
List the 5 most recent open issues tracked in this project.
I believe the command is `bd issues --open --limit 5`.
QUESTION;

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

echo "=== Agent Execution Log ===\n";
echo "Task: List issues using unknown CLI tool (bd)\n\n";

// Execute agent until completion
$finalState = $agent->execute($state);

echo "\n=== Result ===\n";
$answer = $finalState->finalResponse()->toString() ?: 'No answer';
echo "Answer: {$answer}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Tokens: {$finalState->usage()->total()}\n";
echo "Status: {$finalState->status()->value}\n";

if ($rewindLog !== []) {
    echo "\n=== Rewind Log ===\n";
    foreach ($rewindLog as $i => $entry) {
        echo "Rewind #{$i}: checkpoint={$entry['checkpoint']}, at step={$entry['step']}\n";
        echo "  Guidance: {$entry['guidance']}\n";
    }
} else {
    echo "\nNo rewinds occurred — agent completed on first attempt.\n";
}

// Assertions
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
assert($finalState->usage()->total() > 0, 'Expected token usage > 0');
?>
```
