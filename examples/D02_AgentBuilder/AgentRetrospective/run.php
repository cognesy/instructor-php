---
title: 'Agent Execution Retrospective (D-Mail)'
docname: 'agent_retrospective'
order: 8
id: 'f3a1'
tags:
  - 'agent-builder'
  - 'retrospective'
  - 'workflow'
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
- `AgentEventConsoleObserver`: Shows checkpoint injection, tool calls, and step progression

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
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Sandbox\Config\ExecutionPolicy;

// Track rewinds for observability
$rewindLog = [];

// Create console logger for execution visibility
$logger = new AgentEventConsoleObserver(
    useColors: true,
    showTimestamps: true,
    showContinuation: true,
    showToolArgs: true,
);

// Isolated workspace with a small, deterministic `bd` issue-tracker CLI.
// The agent runs arbitrary bash, so it never gets the real repository or the real `bd`:
// PATH is limited to the stub and system binaries, and the environment is not inherited.
$workDir = sys_get_temp_dir() . '/instructor-retrospective-demo';
@mkdir($workDir . '/bin', 0777, true);
file_put_contents($workDir . '/bin/bd', <<<'BASH'
#!/bin/sh
ISSUES='demo-7  open    P1  Retry failed webhook deliveries
demo-6  open    P2  Add CSV export to reports
demo-5  closed  P2  Fix timezone in daily digest
demo-4  open    P3  Document rate limits
demo-3  open    P2  Paginate audit log API
demo-2  open    P1  Rotate leaked staging token
demo-1  closed  P3  Initial project setup'
case "$1 $2" in
  "--help "|"help "|" ")
    echo "bd - lightweight issue tracker"
    echo "Usage: bd <command> [flags]"
    echo "Commands:"
    echo "  list     List issues (see: bd list --help)"
    echo "  show     Show one issue: bd show <id>"
    exit 0 ;;
  "list --help")
    echo "Usage: bd list [--status open|closed] [--limit N]"
    echo "Lists issues, most recent first."
    exit 0 ;;
esac
if [ "$1" = "show" ]; then
  echo "$ISSUES" | grep "^$2 " || { echo "Error: issue '$2' not found" >&2; exit 1; }
  exit 0
fi
if [ "$1" != "list" ]; then
  echo "Error: unknown command \"$1\". Run 'bd --help' for usage." >&2
  exit 1
fi
shift
STATUS=""; LIMIT=50
while [ $# -gt 0 ]; do
  case "$1" in
    --status) STATUS="$2"; shift 2 ;;
    --limit) LIMIT="$2"; shift 2 ;;
    *) echo "Error: unknown flag \"$1\". Run 'bd list --help' for usage." >&2; exit 1 ;;
  esac
done
echo "$ISSUES" | grep -- "$STATUS" | head -n "$LIMIT"
BASH);
chmod($workDir . '/bin/bd', 0755);
$bashPolicy = ExecutionPolicy::in($workDir)
    ->withTimeout(30)
    ->withEnv(['PATH' => $workDir . '/bin:/usr/bin:/bin', 'HOME' => $workDir], inherit: false);

// Build agent with bash + retrospective capabilities
// Note: The system prompt gives NO instructions about `bd` — the agent must explore it.
// The massive --help output becomes wasted context once the agent knows the right command.
// UseExecutionRetrospective automatically appends retrospective instructions
// to the system prompt via BeforeExecution hook — no manual prompt setup needed.
$agent = AgentBuilder::base()
    ->withCapability(new UseLLMConfig(
        llm: LLMProvider::using('anthropic')->withConfigOverrides([
            'model' => 'claude-sonnet-4-5-20250929',
            'maxTokens' => 8192,
        ]),
    ))
    ->withCapability(new UseContextConfig(
        systemPrompt: <<<'SYSTEM'
        You are a CLI automation agent. You accomplish tasks using bash commands.
        Always limit command output — use --limit, | head -20, etc.

        MANDATORY TWO-PASS WORKFLOW — you MUST follow this exactly:

        Pass 1 (EXPLORE): Discover the right command via --help, trial runs, etc.
                Once you know the correct command that produces the answer, STOP.
                Do NOT answer the user's question yet.
                You MUST call execution_retrospective to rewind to checkpoint 1
                with the exact working command as guidance.
                This is REQUIRED even if you already have the answer — the purpose
                is to clean the context of exploration noise.

        Pass 2 (EXECUTE): After rewind, you will see guidance from your future self.
                Trust it completely. Run the command from guidance. Answer. Done.
                Do NOT explore again. Do NOT call execution_retrospective again.

        IMPORTANT: You must ALWAYS rewind after exploration. Never skip the rewind.
        Answering directly after exploration without rewinding is a workflow violation.
        SYSTEM,
    ))
    ->withCapability(new UseBash(policy: $bashPolicy))
    ->withCapability(new UseExecutionRetrospective(
        policy: new RetrospectivePolicy(
            maxRewinds: 1,
            systemPromptInstructions: <<<'PROMPT'
            ## Execution Retrospective (MANDATORY)

            [CHECKPOINT N] markers appear before each step. You have `execution_retrospective`.

            RULES:
            1. After ANY exploration (--help, trial commands, learning), you MUST call
               execution_retrospective to rewind to checkpoint 1 with the working command as guidance.
               This is not optional — it cleans exploration noise from the context.
            2. After a rewind, guidance from your future self appears as an assistant message.
               Trust it completely. Run the command it specifies. Answer. Done.
               Do NOT explore again. Do NOT call execution_retrospective again.
            3. NEVER answer the user's question directly after exploration without rewinding first.
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
List the 5 most recent open issues using the `bd` CLI.
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

if ($finalState->status()->value !== 'completed') {
    echo "Skipping assertions because execution status is {$finalState->status()->value}.\n";
    exit(1);
}

// Assertions
assert($finalState->stepCount() >= 1, 'Expected at least 1 step');
assert($finalState->usage()->total() > 0, 'Expected token usage > 0');
?>
```
