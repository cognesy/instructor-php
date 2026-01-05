<?php

require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Capabilities\File\ListDirTool;
use Cognesy\Addons\Agent\Capabilities\File\SearchFilesTool;
use Cognesy\Addons\Agent\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Schema\Attributes\Description;

/**
 * OODA Cycle with AgentRegistry
 *
 * Demonstrates deterministic multi-phase agent workflow using:
 * - AgentRegistry for specialized phase agents
 * - StructuredOutput for reliable phase outputs
 * - Immutable context for state management
 */

// =============================================================================
// PHASE OUTPUT SCHEMAS
// =============================================================================

#[Description('Summary of observations from raw inputs')]
final class ObserveOutput
{
    #[Description('Concise 2-4 sentence summary of the current situation')]
    public string $summary = '';

    /** @var string[] */
    #[Description('Key facts discovered, as bullet points')]
    public array $keyFacts = [];

    #[Description('What we learned or failed to learn in the previous step')]
    public string $learnings = '';
}

#[Description('Analysis of progress toward goal')]
final class OrientOutput
{
    #[Description('TRUE if we have definitive evidence to answer the goal')]
    public bool $goalAchieved = false;

    #[Description('Assessment of progress toward the goal (0-100 percentage)')]
    public int $progressPercent = 0;

    #[Description('What information is still missing to achieve the goal')]
    public string $whatsMissing = '';

    /** @var string[] */
    #[Description('Possible next actions to close the gap')]
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
        return new self($this->goal, $plan, $this->knowledge, $this->lastActResult, $this->cycle, $this->remainingCycles);
    }

    public function withKnowledge(array $knowledge): self {
        return new self($this->goal, $this->plan, $knowledge, $this->lastActResult, $this->cycle, $this->remainingCycles);
    }

    public function withActResult(string $result): self {
        return new self($this->goal, $this->plan, $this->knowledge, $result, $this->cycle, $this->remainingCycles);
    }

    public function nextCycle(): self {
        return new self($this->goal, $this->plan, $this->knowledge, $this->lastActResult, $this->cycle + 1, $this->remainingCycles - 1);
    }
}

// =============================================================================
// PHASE EXECUTORS
// =============================================================================

final class ObservePhase
{
    public function __invoke(OodaContext $ctx): ObserveOutput {
        $input = $ctx->lastActResult ?? '(first cycle - initial goal received, no previous action)';

        $prompt = <<<PROMPT
            GOAL: {$ctx->goal}
            CYCLE: {$ctx->cycle} (remaining: {$ctx->remainingCycles})

            CURRENT PLAN STATE:
            {$ctx->plan->toPromptContext()}

            RAW INPUT FROM LAST ACTION:
            {$input}

            Summarize concisely: What do we know now? What did we just learn or fail to learn?
            PROMPT;

        $result = (new StructuredOutput())
            ->withMessages($prompt)
            ->withResponseClass(ObserveOutput::class)
            ->get();

        $this->printOutput($result);
        return $result;
    }

    private function printOutput(ObserveOutput $result): void {
        echo "\n[OBSERVE]\n";
        echo "Summary: {$result->summary}\n";
        if ($result->learnings) {
            echo "Learnings: {$result->learnings}\n";
        }
    }
}

final class OrientPhase
{
    public function __invoke(OodaContext $ctx, ObserveOutput $observation): OrientOutput {
        $knowledgeStr = empty($ctx->knowledge)
            ? "(no accumulated knowledge yet)"
            : implode("\n", array_map(fn($k, $i) => "- [{$i}] {$k}", $ctx->knowledge, array_keys($ctx->knowledge)));

        $prompt = <<<PROMPT
            GOAL: {$ctx->goal}
            CYCLE: {$ctx->cycle} (remaining: {$ctx->remainingCycles})

            ACCUMULATED KNOWLEDGE:
            {$knowledgeStr}

            OBSERVER'S SUMMARY:
            {$observation->summary}
            {$observation->learnings}

            CURRENT PLAN STATE:
            {$ctx->plan->toPromptContext()}
            PROMPT;

        $result = (new StructuredOutput())
            ->withMessages($prompt)
            ->withResponseClass(OrientOutput::class)
            ->get();

        $this->printOutput($result);
        return $result;
    }

    private function printOutput(OrientOutput $result): void {
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

final class DecidePhase
{
    public function __invoke(OodaContext $ctx, OrientOutput $analysis): DecideOutput {
        $possibleActionsStr = empty($analysis->possibleActions)
            ? "(none suggested)"
            : implode("\n", array_map(fn($a) => "- {$a}", $analysis->possibleActions));

        $prompt = <<<PROMPT
            GOAL: {$ctx->goal}
            CYCLE: {$ctx->cycle} (remaining: {$ctx->remainingCycles})

            CURRENT PLAN:
            {$ctx->plan->toPromptContext()}

            ANALYST'S ASSESSMENT:
            - Progress: {$analysis->progressPercent}%
            - What's missing: {$analysis->whatsMissing}
            - Possible actions:
            {$possibleActionsStr}
            - Reasoning: {$analysis->reasoning}
            PROMPT;

        $result = (new StructuredOutput())
            ->withMessages($prompt)
            ->withResponseClass(DecideOutput::class)
            ->get();

        $this->printOutput($result);
        return $result;
    }

    private function printOutput(DecideOutput $result): void {
        echo "\n[DECIDE]\n";
        echo "Orders: {$result->orders}\n";
        echo "Success criteria: {$result->successCriteria}\n";
        if ($result->rationale) {
            echo "Rationale: {$result->rationale}\n";
        }
    }
}

final class ActPhase
{
    public function __construct(
        private Agent $agent,
    ) {}

    public function __invoke(OodaContext $ctx, DecideOutput $orders): string {
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

        $result = $this->agent->finalStep($state);
        $response = $result->currentStep()?->outputMessages()->toString() ?? '(no output)';

        echo "\n[ACT]\n";
        echo $response . "\n";

        return $response;
    }
}

// =============================================================================
// REGISTRY BUILDER
// =============================================================================

final class OodaRegistryBuilder
{
    public function __invoke(): AgentRegistry {
        $registry = new AgentRegistry();

        $registry->register($this->observeSpec());
        $registry->register($this->orientSpec());
        $registry->register($this->decideSpec());
        $registry->register($this->actSpec());

        return $registry;
    }

    private function observeSpec(): AgentSpec {
        return new AgentSpec(
            name: 'observe',
            description: 'Information packager for analyst - extracts and summarizes observations',
            systemPrompt: <<<'PROMPT'
                You are the OBSERVE agent in an OODA cycle. Your role is to package selected relevant
                and specific information for the analyst. Do not provide general statements. Extract
                meaningful, concrete facts that are actually helpful for understanding results.

                Cover all important details as you are providing information to a veteran expert in
                the domain. Ask yourself: What would an expert need to know to make informed decisions?
                What would an expert be missing from the information we have?
                PROMPT,
            tools: [],
            model: 'inherit',
        );
    }

    private function orientSpec(): AgentSpec {
        return new AgentSpec(
            name: 'orient',
            description: 'Context and goal-aware analyst - tracks progress and identifies gaps',
            systemPrompt: <<<'PROMPT'
                You are the ORIENT agent in an OODA cycle. Analyze progress toward the goal.

                Have we achieved the goal (have definitive evidence)? If not, what's missing?
                What's the big picture? How would an expert human approach this? What aren't we seeing?
                Be critical and specific - think like a subject matter expert.

                Set goalAchieved to TRUE only if we have concrete, verified evidence to answer the goal.
                If TRUE, provide the finalAnswer. Otherwise, suggest possible next actions.
                PROMPT,
            tools: [],
            model: 'inherit',
        );
    }

    private function decideSpec(): AgentSpec {
        return new AgentSpec(
            name: 'decide',
            description: 'Planner - maintains plan and creates orders for executor',
            systemPrompt: <<<'PROMPT'
                You are the DECIDE agent in an OODA cycle. Issue orders for the executor.

                What is the best next step to make progress toward the goal? Choose one specific,
                actionable step. Define clear success criteria to know when the step is complete.

                The executor has access to: search_files, list_dir, read_file.
                Issue specific, actionable orders. Be precise about what files to search or read.
                PROMPT,
            tools: [],
            model: 'inherit',
        );
    }

    private function actSpec(): AgentSpec {
        return new AgentSpec(
            name: 'act',
            description: 'Executor - uses tools to carry out orders',
            systemPrompt: <<<'PROMPT'
                You are the ACT agent in an OODA cycle. Execute the orders given by the planner.

                You have access to:
                - search_files: Search for files by pattern or content
                - list_dir: List directory contents
                - read_file: Read file contents

                Execute the orders precisely. Report findings clearly. Describe actions taken and their
                results (e.g. files found, content read). If no results, explain what was done or errors.
                PROMPT,
            tools: ['search_files', 'list_dir', 'read_file'],
            model: 'inherit',
        );
    }
}

// =============================================================================
// CYCLE ORCHESTRATOR
// =============================================================================

final class OodaCycle
{
    private ObservePhase $observe;
    private OrientPhase $orient;
    private DecidePhase $decide;
    private ActPhase $act;

    public function __construct(
        private string $workDir,
        private int $maxCycles = 10,
        private string $llmPreset = 'anthropic',
    ) {
        $registry = (new OodaRegistryBuilder())();
        $subagentPolicy = new SubagentPolicy(maxDepth: 3, summaryMaxChars: 8000);
        $builder = AgentBuilder::base()
            ->withCapability(new UseBash())
            ->withCapability(new UseFileTools($this->workDir))
            ->withTools(new Tools(
                SearchFilesTool::inDirectory($this->workDir),
                ListDirTool::inDirectory($this->workDir),
            ))
            ->withCapability(new UseTaskPlanning())
            ->withCapability(new UseSubagents($registry, $subagentPolicy))
            ->withMaxSteps(10);
        if ($this->llmPreset) {
            $builder = $builder->withLlmPreset($this->llmPreset);
        }
        $this->agent = $builder->build();

        $this->observe = new ObservePhase();
        $this->orient = new OrientPhase();
        $this->decide = new DecidePhase();
        $this->act = new ActPhase($this->agent);
    }

    public function __invoke(string $goal): ?string {
        $ctx = new OodaContext(
            goal: $goal,
            plan: new OodaPlan(),
            remainingCycles: $this->maxCycles,
        );

        $this->printHeader($goal);

        while ($ctx->remainingCycles > 0) {
            $this->printCycleHeader($ctx->cycle);

            // OBSERVE
            $observation = ($this->observe)($ctx);
            $ctx = $ctx->withKnowledge([...$ctx->knowledge, "[C{$ctx->cycle}] {$observation->summary}"]);

            // ORIENT
            $analysis = ($this->orient)($ctx, $observation);
            if ($analysis->goalAchieved) {
                $this->printGoalAchieved($ctx->cycle);
                return $analysis->finalAnswer ?: $analysis->reasoning;
            }

            // DECIDE
            $orders = ($this->decide)($ctx, $analysis);
            $ctx = $ctx->withPlan($ctx->plan->withCurrentStep($orders->currentPlanStep));

            // ACT
            $actResult = ($this->act)($ctx, $orders);
            $ctx = $ctx->withActResult($actResult)->nextCycle();
        }

        $this->printCyclesExhausted();
        return null;
    }

    private function printHeader(string $goal): void {
        echo "=" . str_repeat("=", 70) . "\n";
        echo "OODA CYCLE START\n";
        echo "Goal: {$goal}\n";
        echo "=" . str_repeat("=", 70) . "\n";
    }

    private function printCycleHeader(int $cycle): void {
        echo "\n" . str_repeat("-", 35) . " CYCLE {$cycle} " . str_repeat("-", 35) . "\n";
    }

    private function printGoalAchieved(int $cycle): void {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "GOAL ACHIEVED in cycle {$cycle}\n";
        echo str_repeat("=", 70) . "\n";
    }

    private function printCyclesExhausted(): void {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "CYCLES EXHAUSTED - goal not achieved\n";
        echo str_repeat("=", 70) . "\n";
    }
}

// =============================================================================
// MAIN
// =============================================================================

$cycle = new OodaCycle(
    workDir: dirname(__DIR__, 3),
    maxCycles: 10,
    llmPreset: 'anthropic',
);

$goal = "What testing framework does this project use? Find definitive evidence in config files.";

$result = $cycle($goal);

echo "\n" . str_repeat("=", 70) . "\n";
echo "FINAL RESULT:\n";
echo $result ?? "(No result - goal not achieved)";
echo "\n" . str_repeat("=", 70) . "\n";
