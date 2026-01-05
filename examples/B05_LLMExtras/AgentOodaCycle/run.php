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
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\ToolCallStarted;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Schema\Attributes\Description;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * OODA Cycle with AgentRegistry and Professional Logging
 *
 * Demonstrates deterministic multi-phase agent workflow using:
 * - AgentRegistry for specialized phase agents
 * - StructuredOutput for reliable phase outputs
 * - Immutable context for state management
 * - Event-driven logging for visibility into agent operations
 *
 * Logging Features:
 * - Structured, log-inspired output with timestamps
 * - Phase tracking with timing information
 * - Cycle progression visibility
 * - Subagent spawning and completion tracking
 * - Tool call monitoring with arguments and results
 * - Configurable detail level (showSubagentDetails flag)
 * - Indentation for hierarchical operations
 * - Visual indicators (✓, ✗, ▶, ⚡, ⚙) for different event types
 *
 * Example Output:
 * [14:23:45]   [SESSION   ] OODA Cycle Session Started
 * [14:23:45]   [CYCLE     ] Starting cycle 1 (remaining: 10)
 * [14:23:45] ▶ [OBSERVE   ] Phase started
 * [14:23:46]     [OBSERVE   ] summary: Found config files indicating PHPUnit...
 * [14:23:46] ▶ [OBSERVE   ] Phase completed [1.23s]
 * [14:23:46] ⚡ [SUBAGENT  ] Spawning 'act' at depth 1
 * [14:23:46]   ⚙ [TOOL      ] search_files(pattern=*.xml, path=/config)
 * [14:23:47]   ⚙ [TOOL      ] ✓ search_files
 */

// =============================================================================
// STRUCTURED LOGGER
// =============================================================================

final class OodaLogger
{
    private int $indentLevel = 0;
    private array $timers = [];

    public function __construct(
        private bool $showTimestamps = true,
        private bool $showSubagentDetails = true,
    ) {}

    public function cycle(int $cycle, int $remaining): void {
        $this->line();
        $this->log('CYCLE', "Starting cycle {$cycle} (remaining: {$remaining})", 'info');
        $this->line();
    }

    public function phaseStart(string $phase): void {
        $this->startTimer($phase);
        $this->log($phase, "Phase started", 'phase');
        $this->indent();
    }

    public function phaseEnd(string $phase): void {
        $elapsed = $this->stopTimer($phase);
        $this->outdent();
        $this->log($phase, sprintf("Phase completed [%.2fs]", $elapsed), 'phase');
    }

    public function phaseOutput(string $phase, string $key, mixed $value): void {
        $formatted = $this->formatValue($value);
        $this->log($phase, "{$key}: {$formatted}", 'data');
    }

    public function subagentStart(string $name, int $depth): void {
        if (!$this->showSubagentDetails) return;
        $this->log('SUBAGENT', "Spawning '{$name}' at depth {$depth}", 'agent');
        $this->indent();
    }

    public function subagentEnd(string $name, string $status): void {
        if (!$this->showSubagentDetails) return;
        $this->outdent();
        $this->log('SUBAGENT', "'{$name}' {$status}", 'agent');
    }

    public function toolCall(string $toolName, array $args): void {
        if (!$this->showSubagentDetails) return;
        $argsStr = $this->formatArgs($args);
        $this->log('TOOL', "{$toolName}({$argsStr})", 'tool');
    }

    public function toolResult(string $toolName, bool $success): void {
        if (!$this->showSubagentDetails) return;
        $status = $success ? '✓' : '✗';
        $this->log('TOOL', "{$status} {$toolName}", 'tool');
    }

    public function goalAchieved(int $cycle): void {
        $this->line();
        $this->log('SUCCESS', "Goal achieved in cycle {$cycle}", 'success');
        $this->line();
    }

    public function goalFailed(): void {
        $this->line();
        $this->log('FAILURE', "Max cycles exhausted - goal not achieved", 'error');
        $this->line();
    }

    public function sessionStart(string $goal): void {
        $this->line('=');
        $this->log('SESSION', 'OODA Cycle Session Started', 'info');
        $this->log('GOAL', $goal, 'info');
        $this->line('=');
    }

    public function sessionEnd(string $result): void {
        $this->line('=');
        $this->log('SESSION', 'OODA Cycle Session Ended', 'info');
        $this->log('RESULT', $result, 'info');
        $this->line('=');
    }

    private function log(string $component, string $message, string $level = 'info'): void {
        $timestamp = $this->showTimestamps ? $this->timestamp() : '';
        $indent = str_repeat('  ', $this->indentLevel);
        $prefix = $this->levelPrefix($level);
        $componentPadded = str_pad($component, 10);

        echo "{$timestamp}{$indent}{$prefix}[{$componentPadded}] {$message}\n";
    }

    private function timestamp(): string {
        return '[' . date('H:i:s') . '] ';
    }

    private function levelPrefix(string $level): string {
        return match($level) {
            'success' => '✓ ',
            'error' => '✗ ',
            'phase' => '▶ ',
            'agent' => '⚡ ',
            'tool' => '⚙ ',
            'data' => '  ',
            default => '  ',
        };
    }

    private function formatValue(mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }
        if (is_array($value)) {
            return count($value) . ' items';
        }
        if (is_string($value) && strlen($value) > 80) {
            return substr($value, 0, 77) . '...';
        }
        return (string) $value;
    }

    private function formatArgs(array $args): string {
        $parts = [];
        foreach ($args as $key => $value) {
            if (is_string($value) && strlen($value) > 30) {
                $value = substr($value, 0, 27) . '...';
            }
            $parts[] = "{$key}={$value}";
        }
        return implode(', ', $parts);
    }

    private function line(string $char = '-'): void {
        echo str_repeat($char, 80) . "\n";
    }

    private function indent(): void {
        $this->indentLevel++;
    }

    private function outdent(): void {
        $this->indentLevel = max(0, $this->indentLevel - 1);
    }

    private function startTimer(string $key): void {
        $this->timers[$key] = microtime(true);
    }

    private function stopTimer(string $key): float {
        if (!isset($this->timers[$key])) {
            return 0.0;
        }
        $elapsed = microtime(true) - $this->timers[$key];
        unset($this->timers[$key]);
        return $elapsed;
    }
}

// =============================================================================
// EVENT WIRETAP
// =============================================================================

final class OodaWiretap
{
    public function __construct(
        private OodaLogger $logger,
    ) {}

    public function attachTo(EventDispatcher $events): void {
        $events->addListener(AgentStepStarted::class, function(AgentStepStarted $event) {
            // Only log subagent activity (when parentAgentId is set)
            if ($event->parentAgentId !== null) {
                $depth = substr_count($event->agentId, '-');
                $this->logger->subagentStart(
                    substr($event->agentId, 0, 12),
                    $depth
                );
            }
        });

        $events->addListener(AgentStepCompleted::class, function(AgentStepCompleted $event) {
            // Only log subagent activity (when parentAgentId is set)
            if ($event->parentAgentId !== null) {
                $this->logger->subagentEnd(
                    substr($event->agentId, 0, 12),
                    'completed'
                );
            }
        });

        $events->addListener(ToolCallStarted::class, function(ToolCallStarted $event) {
            $args = is_array($event->toolArgs) ? $event->toolArgs : [];
            $this->logger->toolCall($event->toolName, $args);
        });

        $events->addListener(ToolCallCompleted::class, function(ToolCallCompleted $event) {
            $this->logger->toolResult($event->toolName, $event->isSuccess);
        });
    }
}

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
    public function __construct(
        private OodaLogger $logger,
    ) {}

    public function __invoke(OodaContext $ctx): ObserveOutput {
        $this->logger->phaseStart('OBSERVE');

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

        $this->logOutput($result);
        $this->logger->phaseEnd('OBSERVE');

        return $result;
    }

    private function logOutput(ObserveOutput $result): void {
        $this->logger->phaseOutput('OBSERVE', 'summary', $result->summary);
        if (!empty($result->keyFacts)) {
            $this->logger->phaseOutput('OBSERVE', 'key_facts', $result->keyFacts);
        }
        if ($result->learnings) {
            $this->logger->phaseOutput('OBSERVE', 'learnings', $result->learnings);
        }
    }
}

final class OrientPhase
{
    public function __construct(
        private OodaLogger $logger,
    ) {}

    public function __invoke(OodaContext $ctx, ObserveOutput $observation): OrientOutput {
        $this->logger->phaseStart('ORIENT');

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

        $this->logOutput($result);
        $this->logger->phaseEnd('ORIENT');

        return $result;
    }

    private function logOutput(OrientOutput $result): void {
        $this->logger->phaseOutput('ORIENT', 'goal_achieved', $result->goalAchieved);
        $this->logger->phaseOutput('ORIENT', 'progress', "{$result->progressPercent}%");
        $this->logger->phaseOutput('ORIENT', 'reasoning', $result->reasoning);

        if (!$result->goalAchieved && $result->whatsMissing) {
            $this->logger->phaseOutput('ORIENT', 'missing', $result->whatsMissing);
        }

        if ($result->goalAchieved && $result->finalAnswer) {
            $this->logger->phaseOutput('ORIENT', 'final_answer', $result->finalAnswer);
        }
    }
}

final class DecidePhase
{
    public function __construct(
        private OodaLogger $logger,
    ) {}

    public function __invoke(OodaContext $ctx, OrientOutput $analysis): DecideOutput {
        $this->logger->phaseStart('DECIDE');

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

        $this->logOutput($result);
        $this->logger->phaseEnd('DECIDE');

        return $result;
    }

    private function logOutput(DecideOutput $result): void {
        $this->logger->phaseOutput('DECIDE', 'orders', $result->orders);
        $this->logger->phaseOutput('DECIDE', 'success_criteria', $result->successCriteria);
        if ($result->currentPlanStep) {
            $this->logger->phaseOutput('DECIDE', 'plan_step', $result->currentPlanStep);
        }
        if ($result->rationale) {
            $this->logger->phaseOutput('DECIDE', 'rationale', $result->rationale);
        }
    }
}

final class ActPhase
{
    public function __construct(
        private Agent $agent,
        private OodaLogger $logger,
    ) {}

    public function __invoke(OodaContext $ctx, DecideOutput $orders): string {
        $this->logger->phaseStart('ACT');

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

        $this->logger->phaseOutput('ACT', 'result', $response);
        $this->logger->phaseEnd('ACT');

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
    private OodaLogger $logger;
    private Agent $agent;

    public function __construct(
        private string $workDir,
        private int $maxCycles = 10,
        private string $llmPreset = 'anthropic',
        private bool $showSubagentDetails = true,
    ) {
        $this->logger = new OodaLogger(
            showTimestamps: true,
            showSubagentDetails: $this->showSubagentDetails,
        );

        $registry = (new OodaRegistryBuilder())();
        $subagentPolicy = new SubagentPolicy(maxDepth: 3, summaryMaxChars: 8000);

        // Create event dispatcher and attach wiretap
        $events = new EventDispatcher();
        $wiretap = new OodaWiretap($this->logger);
        $wiretap->attachTo($events);

        $builder = AgentBuilder::base()
            ->withCapability(new UseBash())
            ->withCapability(new UseFileTools($this->workDir))
            ->withTools(new Tools(
                SearchFilesTool::inDirectory($this->workDir),
                ListDirTool::inDirectory($this->workDir),
            ))
            ->withCapability(new UseTaskPlanning())
            ->withCapability(new UseSubagents($registry, $subagentPolicy))
            ->withMaxSteps(10)
            ->withEventDispatcher($events);

        if ($this->llmPreset) {
            $builder = $builder->withLlmPreset($this->llmPreset);
        }

        $this->agent = $builder->build();

        $this->observe = new ObservePhase($this->logger);
        $this->orient = new OrientPhase($this->logger);
        $this->decide = new DecidePhase($this->logger);
        $this->act = new ActPhase($this->agent, $this->logger);
    }

    public function __invoke(string $goal): ?string {
        $ctx = new OodaContext(
            goal: $goal,
            plan: new OodaPlan(),
            remainingCycles: $this->maxCycles,
        );

        $this->logger->sessionStart($goal);

        while ($ctx->remainingCycles > 0) {
            $this->logger->cycle($ctx->cycle, $ctx->remainingCycles);

            // OBSERVE
            $observation = ($this->observe)($ctx);
            $ctx = $ctx->withKnowledge([...$ctx->knowledge, "[C{$ctx->cycle}] {$observation->summary}"]);

            // ORIENT
            $analysis = ($this->orient)($ctx, $observation);
            if ($analysis->goalAchieved) {
                $this->logger->goalAchieved($ctx->cycle);
                $finalResult = $analysis->finalAnswer ?: $analysis->reasoning;
                $this->logger->sessionEnd($finalResult);
                return $finalResult;
            }

            // DECIDE
            $orders = ($this->decide)($ctx, $analysis);
            $ctx = $ctx->withPlan($ctx->plan->withCurrentStep($orders->currentPlanStep));

            // ACT
            $actResult = ($this->act)($ctx, $orders);
            $ctx = $ctx->withActResult($actResult)->nextCycle();
        }

        $this->logger->goalFailed();
        $this->logger->sessionEnd('(No result - goal not achieved)');
        return null;
    }
}

// =============================================================================
// MAIN
// =============================================================================

$cycle = new OodaCycle(
    workDir: dirname(__DIR__, 3),
    maxCycles: 10,
    llmPreset: 'anthropic',
    showSubagentDetails: true,  // Set to false to hide subagent/tool details
);

$goal = "What testing framework does this project use? Find definitive evidence in config files.";

$result = $cycle($goal);
