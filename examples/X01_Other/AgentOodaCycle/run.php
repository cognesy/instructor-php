<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Schema\Attributes\Description;

/**
 * OODA Cycle with SubagentRegistry
 *
 * Demonstrates using SubagentRegistry for complex multi-phase workflows.
 *
 * Cycles through 4 specialized subagents:
 * 1. OBSERVE - Information packager, summarizes inputs for analyst
 * 2. ORIENT  - Context-aware analyst, tracks progress and identifies gaps
 * 3. DECIDE  - Planner, maintains plan and creates orders for executor
 * 4. ACT     - Executor, uses tools and returns results
 *
 * Each phase is defined as a SubagentSpec and spawned via spawn_subagent.
 * State (goal, plan, knowledge) persists across the cycle.
 * Phase outputs are extracted using StructuredOutput for reliability.
 */

// =============================================================================
// DATA STRUCTURES - Phase Outputs
// =============================================================================

#[Description('Summary of observations from raw inputs')]
final class ObserveOutput
{
    #[Description('Concise 2-4 sentence summary of the current situation')]
    public string $summary = '';

    #[Description('Key facts discovered, as bullet points')]
    /** @var string[] */
    public array $keyFacts = [];

    #[Description('What we learned or failed to learn in the previous step')]
    public string $learnings = '';
}

#[Description('Analysis of progress toward goal')]
final class OrientOutput
{
    #[Description('TRUE if we have definitive evidence to answer the goal, FALSE otherwise')]
    public bool $goalAchieved = false;

    #[Description('Assessment of progress toward the goal (0-100 percentage)')]
    public int $progressPercent = 0;

    #[Description('What information is still missing to achieve the goal')]
    public string $whatsMissing = '';

    #[Description('Possible next actions to close the gap')]
    /** @var string[] */
    public array $possibleActions = [];

    #[Description('The final answer if goalAchieved is TRUE, otherwise empty')]
    public string $finalAnswer = '';

    #[Description('Brief reasoning for the assessment')]
    public string $reasoning = '';
}

#[Description('Orders for the executor agent')]
final class DecideOutput
{
    #[Description('Specific, actionable orders for the executor to carry out')]
    public string $orders = '';

    #[Description('How we will know the orders succeeded')]
    public string $successCriteria = '';

    #[Description('Current step in the plan being executed')]
    public string $currentPlanStep = '';

    #[Description('Brief rationale for choosing this action')]
    public string $rationale = '';
}

// =============================================================================
// PERSISTENT STATE
// =============================================================================

final class OodaPlan
{
    public function __construct(
        public array $steps = [],
        public array $completedSteps = [],
        public ?string $currentStep = null,
        public array $failedApproaches = [],
    ) {}

    public function toPromptContext(): string {
        if (empty($this->steps) && $this->currentStep === null) {
            return "No plan established yet.";
        }

        $lines = [];
        if (!empty($this->completedSteps)) {
            $lines[] = "Completed: " . implode(", ", $this->completedSteps);
        }
        if ($this->currentStep !== null) {
            $lines[] = "Current: {$this->currentStep}";
        }
        if (!empty($this->steps)) {
            $lines[] = "Remaining: " . implode(" -> ", $this->steps);
        }
        if (!empty($this->failedApproaches)) {
            $lines[] = "Failed approaches: " . implode("; ", $this->failedApproaches);
        }
        return implode("\n", $lines);
    }

    public function withCurrentStep(string $step): self {
        $new = clone $this;
        $new->currentStep = $step;
        return $new;
    }
}

final class OodaContext
{
    public function __construct(
        public readonly string $goal,
        public readonly OodaPlan $plan,
        public readonly array $knowledge = [],
        public readonly ?string $lastActResult = null,
        public readonly int $cycle = 1,
        public readonly int $remainingCycles = 10,
    ) {}

    public function withPlan(OodaPlan $plan): self {
        return new self(
            goal: $this->goal,
            plan: $plan,
            knowledge: $this->knowledge,
            lastActResult: $this->lastActResult,
            cycle: $this->cycle,
            remainingCycles: $this->remainingCycles,
        );
    }

    public function withKnowledge(array $knowledge): self {
        return new self(
            goal: $this->goal,
            plan: $this->plan,
            knowledge: $knowledge,
            lastActResult: $this->lastActResult,
            cycle: $this->cycle,
            remainingCycles: $this->remainingCycles,
        );
    }

    public function withActResult(string $result): self {
        return new self(
            goal: $this->goal,
            plan: $this->plan,
            knowledge: $this->knowledge,
            lastActResult: $result,
            cycle: $this->cycle,
            remainingCycles: $this->remainingCycles,
        );
    }

    public function nextCycle(): self {
        return new self(
            goal: $this->goal,
            plan: $this->plan,
            knowledge: $this->knowledge,
            lastActResult: $this->lastActResult,
            cycle: $this->cycle + 1,
            remainingCycles: $this->remainingCycles - 1,
        );
    }
}

// =============================================================================
// SUBAGENT REGISTRY FACTORY
// =============================================================================

final class OodaSubagentRegistryFactory
{
    public function create(): AgentRegistry {
        $registry = new AgentRegistry();

        $registry->register(new AgentSpec(
            name: 'observe',
            description: 'Information packager for analyst - extracts and summarizes observations',
            systemPrompt: $this->observePrompt(),
            tools: [],
            model: 'inherit',
        ));

        $registry->register(new AgentSpec(
            name: 'orient',
            description: 'Context and goal-aware analyst - tracks progress and identifies gaps',
            systemPrompt: $this->orientPrompt(),
            tools: [],
            model: 'inherit',
        ));

        $registry->register(new AgentSpec(
            name: 'decide',
            description: 'Planner - maintains plan and creates orders for executor',
            systemPrompt: $this->decidePrompt(),
            tools: [],
            model: 'inherit',
        ));

        $registry->register(new AgentSpec(
            name: 'act',
            description: 'Executor - uses tools to carry out orders',
            systemPrompt: $this->actPrompt(),
            tools: ['search_files', 'list_dir', 'read_file'],
            model: 'inherit',
        ));

        return $registry;
    }

    private function observePrompt(): string {
        return <<<'PROMPT'
You are the OBSERVE agent in an OODA cycle. Your role is to package selected relevant
and specific information for the analyst. Do not provide general statements. Extract
meaningful, concrete facts that are actually helpful for understanding results.

Cover all important details as you are providing information to a veteran expert in
the domain. Ask yourself: What would an expert need to know to make informed decisions?
What would an expert be missing from the information we have? Stating perceived gaps
may help the analyst focus better.

Be aware of the context and goal - are we just starting, what are we trying to achieve?
PROMPT;
    }

    private function orientPrompt(): string {
        return <<<'PROMPT'
You are the ORIENT agent in an OODA cycle. Analyze progress toward the goal.

Analyze: Have we achieved the goal (have definitive evidence)? If not, what's missing?
What's the big picture? How would an expert human approach this? What aren't we seeing?
What mistakes have we made and how can we reorient? Provide your reasoning and advice
using deep domain expertise. Be critical and specific - think like a subject matter
expert, avoid misjudgments based on superficial information.

Sometimes we need to step back and ask ourselves - what is unstated here? What
assumptions are we making?

Set goalAchieved to TRUE only if we have concrete, verified evidence to answer the goal.
If TRUE, provide the finalAnswer. Otherwise, suggest possible next actions.
PROMPT;
    }

    private function decidePrompt(): string {
        return <<<'PROMPT'
You are the DECIDE agent in an OODA cycle. Issue orders for the executor.

Think how an expert human would approach this situation. Review the current plan.
What is the best next step to make progress toward the goal? Choose one specific,
actionable step or steps. Define clear success criteria to know when the step is complete.
Think how to verify success reliably. Provide your reasoning.

The executor has access to: search_files, list_dir, read_file.
Issue specific, actionable orders. Be precise about what files to search or read.
PROMPT;
    }

    private function actPrompt(): string {
        return <<<'PROMPT'
You are the ACT agent in an OODA cycle. Execute the orders given by the planner.

You have access to:
- search_files: Search for files by pattern or content
- list_dir: List directory contents
- read_file: Read file contents

Execute the orders precisely. Report findings clearly. Describe actions taken and their
results (e.g. files found, content read). If no results, explain what was done or what
errors occurred. Ask yourself: in case I fail here - how can I provide as much useful
details as possible to help the next cycle?
PROMPT;
    }
}

// =============================================================================
// PHASE EXECUTORS
// =============================================================================

final class ObservePhase
{
    public function __invoke(OodaContext $ctx): ObserveOutput {
        $input = $ctx->lastActResult ?? '(first cycle - initial goal received, no previous action)';
        $planContext = $ctx->plan->toPromptContext();

        $prompt = <<<PROMPT
GOAL: {$ctx->goal}
CYCLE: {$ctx->cycle} (remaining: {$ctx->remainingCycles})

CURRENT PLAN STATE:
{$planContext}

RAW INPUT FROM LAST ACTION:
{$input}

Summarize concisely: What do we know now? What did we just learn or fail to learn?
PROMPT;

        return (new StructuredOutput())
            ->withMessages($prompt)
            ->withResponseClass(ObserveOutput::class)
            ->get();
    }
}

final class OrientPhase
{
    public function __invoke(OodaContext $ctx, ObserveOutput $observation): OrientOutput {
        $knowledgeStr = empty($ctx->knowledge)
            ? "(no accumulated knowledge yet)"
            : implode("\n", array_map(
                fn($k, $i) => "- [{$i}] {$k}",
                $ctx->knowledge,
                array_keys($ctx->knowledge)
            ));

        $planContext = $ctx->plan->toPromptContext();

        $prompt = <<<PROMPT
GOAL: {$ctx->goal}
CYCLE: {$ctx->cycle} (remaining: {$ctx->remainingCycles})

ACCUMULATED KNOWLEDGE:
{$knowledgeStr}

OBSERVER'S SUMMARY:
{$observation->summary}
{$observation->learnings}

CURRENT PLAN STATE:
{$planContext}
PROMPT;

        return (new StructuredOutput())
            ->withMessages($prompt)
            ->withResponseClass(OrientOutput::class)
            ->get();
    }
}

final class DecidePhase
{
    public function __invoke(OodaContext $ctx, OrientOutput $analysis): DecideOutput {
        $planContext = $ctx->plan->toPromptContext();

        $possibleActionsStr = empty($analysis->possibleActions)
            ? "(none suggested)"
            : implode("\n", array_map(fn($a) => "- {$a}", $analysis->possibleActions));

        $prompt = <<<PROMPT
GOAL: {$ctx->goal}
CYCLE: {$ctx->cycle} (remaining: {$ctx->remainingCycles})

CURRENT PLAN:
{$planContext}

ANALYST'S ASSESSMENT:
- Progress: {$analysis->progressPercent}%
- What's missing: {$analysis->whatsMissing}
- Possible actions:
{$possibleActionsStr}
- Reasoning: {$analysis->reasoning}
PROMPT;

        return (new StructuredOutput())
            ->withMessages($prompt)
            ->withResponseClass(DecideOutput::class)
            ->get();
    }
}

final class ActPhase
{
    public function __invoke(Agent $agent, OodaContext $ctx, DecideOutput $orders): string {
        $task = <<<TASK
GOAL: {$ctx->goal}

ORDERS FROM PLANNER:
{$orders->orders}

SUCCESS CRITERIA:
{$orders->successCriteria}

Use the 'act' subagent to execute these orders using the available file exploration tools.
Report back with what was found or what errors occurred.
TASK;

        $state = AgentState::empty()->withMessages(
            Messages::fromString("Spawn the 'act' subagent with this task:\n\n{$task}")
        );

        $result = $agent->finalStep($state);
        return $result->currentStep()?->outputMessages()->toString() ?? '(no output)';
    }
}

// =============================================================================
// PHASE PRINTERS
// =============================================================================

final class ObservePhasePrinter
{
    public function __invoke(ObserveOutput $result): void {
        echo "\n[OBSERVE]\n";
        echo "Summary: {$result->summary}\n";
        if (!empty($result->keyFacts)) {
            echo "Facts: " . implode("; ", $result->keyFacts) . "\n";
        }
        if ($result->learnings) {
            echo "Learnings: {$result->learnings}\n";
        }
    }
}

final class OrientPhasePrinter
{
    public function __invoke(OrientOutput $result): void {
        echo "\n[ORIENT]\n";
        echo "Goal achieved: " . ($result->goalAchieved ? "YES" : "NO") . "\n";
        echo "Progress: {$result->progressPercent}%\n";
        echo "Reasoning: {$result->reasoning}\n";
        if (!$result->goalAchieved && $result->whatsMissing) {
            echo "Missing: {$result->whatsMissing}\n";
        }
        if ($result->goalAchieved && $result->finalAnswer) {
            echo "Final answer: {$result->finalAnswer}\n";
        }
    }
}

final class DecidePhasePrinter
{
    public function __invoke(DecideOutput $result): void {
        echo "\n[DECIDE]\n";
        echo "Orders: {$result->orders}\n";
        echo "Success criteria: {$result->successCriteria}\n";
        if ($result->rationale) {
            echo "Rationale: {$result->rationale}\n";
        }
    }
}

final class ActPhasePrinter
{
    public function __invoke(string $response): void {
        echo "\n[ACT]\n";
        echo $response . "\n";
    }
}

// =============================================================================
// OODA CYCLE ORCHESTRATOR
// =============================================================================

final class OodaCycleOrchestrator
{
    private ObservePhase $observePhase;
    private OrientPhase $orientPhase;
    private DecidePhase $decidePhase;
    private ActPhase $actPhase;

    private ObservePhasePrinter $observePrinter;
    private OrientPhasePrinter $orientPrinter;
    private DecidePhasePrinter $decidePrinter;
    private ActPhasePrinter $actPrinter;

    public function __construct(
        private Agent $agent
    ) {
        $this->observePhase = new ObservePhase();
        $this->orientPhase = new OrientPhase();
        $this->decidePhase = new DecidePhase();
        $this->actPhase = new ActPhase();

        $this->observePrinter = new ObservePhasePrinter();
        $this->orientPrinter = new OrientPhasePrinter();
        $this->decidePrinter = new DecidePhasePrinter();
        $this->actPrinter = new ActPhasePrinter();
    }

    public function run(string $goal, int $maxCycles): ?string {
        $ctx = new OodaContext(
            goal: $goal,
            plan: new OodaPlan(),
            knowledge: [],
            lastActResult: null,
            cycle: 1,
            remainingCycles: $maxCycles,
        );

        while ($ctx->remainingCycles > 0) {
            $this->printCycleHeader($ctx->cycle);

            $observation = ($this->observePhase)($ctx);
            ($this->observePrinter)($observation);

            $knowledge = $ctx->knowledge;
            $knowledge[] = "[C{$ctx->cycle}] " . $observation->summary;
            $ctx = $ctx->withKnowledge($knowledge);

            $analysis = ($this->orientPhase)($ctx, $observation);
            ($this->orientPrinter)($analysis);

            if ($analysis->goalAchieved) {
                return $analysis->finalAnswer ?: $analysis->reasoning;
            }

            $orders = ($this->decidePhase)($ctx, $analysis);
            ($this->decidePrinter)($orders);

            $ctx = $ctx->withPlan($ctx->plan->withCurrentStep($orders->currentPlanStep));

            $actResult = ($this->actPhase)($this->agent, $ctx, $orders);
            ($this->actPrinter)($actResult);

            $ctx = $ctx->withActResult($actResult);
            $ctx = $ctx->nextCycle();
        }

        return null;
    }

    private function printCycleHeader(int $cycle): void {
        echo "\n" . str_repeat("-", 35) . " CYCLE {$cycle} " . str_repeat("-", 35) . "\n";
    }
}

// =============================================================================
// HEADER AND FOOTER PRINTERS
// =============================================================================

final class OodaHeaderPrinter
{
    public function __invoke(string $goal): void {
        echo "=" . str_repeat("=", 70) . "\n";
        echo "OODA CYCLE START (using SubagentRegistry)\n";
        echo "Goal: {$goal}\n";
        echo "=" . str_repeat("=", 70) . "\n";

        echo "\nThis example demonstrates SubagentRegistry with OODA cycle:\n";
        echo "- 4 specialized subagents (observe, orient, decide, act)\n";
        echo "- Each phase defined as a SubagentSpec\n";
        echo "- ACT phase spawned with tools via spawn_subagent\n";
        echo "- Observe/Orient/Decide use StructuredOutput for reliability\n\n";
    }
}

final class OodaResultPrinter
{
    public function __invoke(?string $result, bool $goalAchieved): void {
        echo "\n" . str_repeat("=", 70) . "\n";
        if ($goalAchieved) {
            echo "GOAL ACHIEVED\n";
        } else {
            echo "CYCLES EXHAUSTED - goal not achieved\n";
        }
        echo str_repeat("=", 70) . "\n\n";

        echo "FINAL RESULT:\n";
        echo $result ?? "(No result - goal not achieved)";
        echo "\n" . str_repeat("=", 70) . "\n\n";

        echo "Key SubagentRegistry features demonstrated:\n";
        echo "- Define specialized agents (observe, orient, decide, act)\n";
        echo "- Tool filtering (act has tools, others don't)\n";
        echo "- Model inheritance (all use 'inherit')\n";
        echo "- Complex orchestration patterns\n";
    }
}

// =============================================================================
// MAIN
// =============================================================================

$projectRoot = dirname(__DIR__, 3);
$goal = "What testing framework does this project use? Find definitive evidence in config files.";

$registryFactory = new OodaSubagentRegistryFactory();
$registry = $registryFactory->create();

$agent = AgentBuilder::new()
    ->withBash()
    ->withFileTools($projectRoot)
    ->withTaskPlanning()
    ->withSubagents($registry, 3)
    ->withMaxSteps(10)
    ->withLlmPreset('anthropic')
    ->build();

$headerPrinter = new OodaHeaderPrinter();
$headerPrinter($goal);

echo "(Commented out - uncomment to actually run with API)\n\n";

// Uncomment to actually run (requires API key and takes time)
/*
$orchestrator = new OodaCycleOrchestrator($agent);
$result = $orchestrator->run($goal, maxCycles: 10);

$resultPrinter = new OodaResultPrinter();
$resultPrinter($result, $result !== null);
*/
