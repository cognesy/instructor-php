---
title: 'OODA Cycle Agent Pattern'
docname: 'agent_ooda_cycle'
---

## Overview

The OODA (Observe-Orient-Decide-Act) cycle is a military decision-making model adapted for
AI agents. This pattern breaks complex tasks into four distinct phases that repeat until
the objective is achieved. Each phase has a specific purpose and output structure.

This example demonstrates:
- Multi-phase agent workflow with structured outputs
- Immutable context passing between phases
- Agent execution in the ACT phase with tool use
- Event-driven logging for visibility into operations
- Distinction between LLM inference (OBSERVE/ORIENT/DECIDE) and agent execution (ACT)

Key concepts:
- **Observe**: Gather information about current situation
- **Orient**: Analyze information and understand context
- **Decide**: Determine what actions to take
- **Act**: Execute decided actions using agent with tools
- **Cycle**: Repeat until objective achieved or max cycles reached

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Schema\Attributes\Description;

// Phase output classes
class ObserveOutput {
    #[Description('What you observed about the current situation')]
    public string $summary;
    #[Description('Key findings or patterns discovered')]
    public array $findings;
}

class OrientOutput {
    #[Description('Your analysis of the observed information')]
    public string $analysis;
    #[Description('Current understanding of the situation')]
    public string $understanding;
}

class DecideOutput {
    #[Description('Specific orders for what needs to be done')]
    public string $orders;
    #[Description('Why this action was chosen')]
    public string $reasoning;
}

// Context passed between phases
class OodaContext {
    public function __construct(
        public string $objective,
        public string $workDir,
        public array $history = [],
    ) {}

    public function withPhaseResult(string $phase, mixed $result): self {
        $new = clone $this;
        $new->history[] = ['phase' => $phase, 'result' => $result];
        return $new;
    }
}

// OODA Cycle implementation
class OodaCycle {
    private int $maxCycles;

    public function __construct(int $maxCycles = 5) {
        $this->maxCycles = $maxCycles;
    }

    public function run(string $objective, string $workDir): string {
        $ctx = new OodaContext($objective, $workDir);

        for ($cycle = 1; $cycle <= $this->maxCycles; $cycle++) {
            echo "Cycle {$cycle}/{$this->maxCycles}\n\n";

            // OBSERVE: Gather information (LLM inference)
            echo "▶ OBSERVE\n";
            $observe = $this->observe($ctx);
            $ctx = $ctx->withPhaseResult('observe', $observe);
            echo "  Summary: {$observe->summary}\n\n";

            // ORIENT: Analyze information (LLM inference)
            echo "▶ ORIENT\n";
            $orient = $this->orient($ctx, $observe);
            $ctx = $ctx->withPhaseResult('orient', $orient);
            echo "  Analysis: {$orient->analysis}\n\n";

            // DECIDE: Determine actions (LLM inference)
            echo "▶ DECIDE\n";
            $decide = $this->decide($ctx, $orient);
            $ctx = $ctx->withPhaseResult('decide', $decide);
            echo "  Orders: {$decide->orders}\n\n";

            // ACT: Execute actions (Agent with tools)
            echo "▶ ACT\n";
            $result = $this->act($ctx, $decide);
            echo "  Result: {$result}\n\n";

            // Check if objective achieved
            if ($this->isObjectiveAchieved($result, $objective)) {
                echo "✓ Objective achieved\n";
                return $result;
            }
        }

        return "Max cycles reached";
    }

    private function observe(OodaContext $ctx): ObserveOutput {
        $prompt = "Objective: {$ctx->objective}\nWhat do you observe about the current situation?";

        return (new StructuredOutput())
            ->withMessages(Messages::fromString($prompt))
            ->withResponseClass(ObserveOutput::class)
            ->get();
    }

    private function orient(OodaContext $ctx, ObserveOutput $observe): OrientOutput {
        $prompt = "Objective: {$ctx->objective}\nObservations: {$observe->summary}\nAnalyze this information.";

        return (new StructuredOutput())
            ->withMessages(Messages::fromString($prompt))
            ->withResponseClass(OrientOutput::class)
            ->get();
    }

    private function decide(OodaContext $ctx, OrientOutput $orient): DecideOutput {
        $prompt = "Objective: {$ctx->objective}\nAnalysis: {$orient->analysis}\nWhat should we do?";

        return (new StructuredOutput())
            ->withMessages(Messages::fromString($prompt))
            ->withResponseClass(DecideOutput::class)
            ->get();
    }

    private function act(OodaContext $ctx, DecideOutput $decide): string {
        // Build agent with file tools for ACT phase
        $agent = AgentBuilder::base()
            ->withCapability(new UseFileTools($ctx->workDir))
            ->build();

        $state = AgentState::empty()->withMessages(
            Messages::fromString($decide->orders)
        );

        $finalState = $agent->finalStep($state);

        return $finalState->currentStep()?->outputMessages()->toString() ?? 'No output';
    }

    private function isObjectiveAchieved(string $result, string $objective): bool {
        // Simplified check - real implementation would be more sophisticated
        return str_contains(strtolower($result), 'complete') ||
               str_contains(strtolower($result), 'found');
    }
}

// Run OODA cycle
$cycle = new OodaCycle(maxCycles: 3);
$workDir = dirname(__DIR__, 3);

$result = $cycle->run(
    objective: "Find all PHP test files in the project",
    workDir: $workDir
);

echo "\nFinal Result:\n{$result}\n";
?>
```

## Expected Output

```
Cycle 1/3
▶ OBSERVE
  Summary: Need to search for PHP test files in the project structure
▶ ORIENT
  Analysis: Test files are typically in tests/ or have Test suffix in filename
▶ DECIDE
  Orders: Search for files matching *Test.php pattern in all directories
▶ ACT
  Result: Found 240 test files in packages/addons/tests/ directory
✓ Objective achieved
Final Result:
Found 240 test files in packages/addons/tests/ directory including:
- Feature tests in Feature/
- Unit tests in Unit/
- Integration tests in Integration/
```

## Key Points

- **Four distinct phases**: Each phase has a specific purpose and structured output
- **LLM vs Agent**: OBSERVE/ORIENT/DECIDE use direct LLM inference, ACT uses agent with tools
- **Immutable context**: Context object passed between phases contains history
- **Structured outputs**: Each phase returns typed objects for reliable data flow
- **Agent in ACT**: Only the ACT phase spawns agents with tool capabilities
- **Cycle iteration**: Loop repeats until objective achieved or max cycles reached
- **Tool use visibility**: ACT phase shows which tools the agent calls
- **State management**: Each phase builds on previous phase results
- **Use cases**: Complex multi-step tasks, autonomous research, code analysis, planning workflows
