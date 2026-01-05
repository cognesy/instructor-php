<?php

require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Addons\Agent\Capabilities\Bash\UseBash;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\Tasks\UseTaskPlanning;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Capabilities\Subagent\SpawnSubagentTool;
use Cognesy\Addons\Agent\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Messages\Messages;

/**
 * Subagent Example: Context Isolation
 *
 * Demonstrates the KEY VALUE of subagents:
 * - Main agent coordinates reviews of 3 classes
 * - Each review uses an isolated subagent
 * - Main agent only receives summaries (not full review details)
 * - Shows: context stays clean, scalable to many reviews
 *
 * Scope: Fast execution - reviews 3 small classes concisely
 */

// =============================================================================
// AGENT REGISTRY
// =============================================================================

final class QuickReviewAgentRegistry
{
    public function create(): AgentRegistry {
        $registry = new AgentRegistry();

        $registry->register(new AgentSpec(
            name: 'quick-reviewer',
            description: 'Fast code reviewer - 1 finding per class',
            systemPrompt: <<<'PROMPT'
You are a code reviewer. Read the file and return ONE line only.

Format: ClassName: Issue - Fix
Example: UserAuth: Missing type hints - add string return type

Return ONLY ONE LINE. No explanations, no markdown, no extra text.
PROMPT,
            tools: ['read_file'],
            model: 'inherit',
        ));

        return $registry;
    }
}

// =============================================================================
// PRINTERS
// =============================================================================

final class ReviewProgressPrinter
{
    private int $stepNum = 0;

    public function __invoke(AgentStep $step): void {
        $this->stepNum++;

        if ($step->hasErrors()) {
            foreach ($step->errors() as $error) {
                echo "⚠ ERROR: " . $error->getMessage() . "\n";
            }
        }

        if ($step->hasToolCalls()) {
            foreach ($step->toolCalls()->all() as $toolCall) {
                if ($toolCall->name() === 'spawn_subagent') {
                    echo "→ Spawning review subagent\n";
                }
            }
        }

        // DEBUG: Show tool execution results
        if ($step->toolExecutions()->hasExecutions()) {
            foreach ($step->toolExecutions()->all() as $execution) {
                if ($execution->name() === 'spawn_subagent' && !$execution->hasError()) {
                    $result = $execution->value();
                    $resultStr = is_string($result) ? $result : json_encode($result);
                    echo "  [DEBUG] Tool result length: " . strlen($resultStr) . " chars\n";
                    echo "  [DEBUG] Tool result: " . substr($resultStr, 0, 200) . "...\n";
                }
            }
        }

        // DEBUG: Show step usage
        echo "  [DEBUG] Step input messages: " . $step->inputMessages()->count() . "\n";
        echo "  [DEBUG] Step output messages: " . $step->outputMessages()->count() . "\n";
    }
}

final class ReviewResultPrinter
{
    public function __invoke(AgentState $state): void {
        echo "\n" . str_repeat("═", 68) . "\n\n";
        $this->printResult($state);
        $this->printContextAnalysis($state);
        $this->printStats($state);
    }

    private function printResult(AgentState $state): void {
        $response = $state->currentStep()?->outputMessages()->toString() ?? '';

        echo "COORDINATOR RESULT:\n\n";
        if (empty($response)) {
            echo "(No response - check errors)\n";
        } else {
            echo $response . "\n";
        }
        echo "\n" . str_repeat("─", 68) . "\n\n";
    }

    private function printContextAnalysis(AgentState $state): void {
        $mainAgentTokens = $state->usage()->total();
        $subagentTokenUsage = $this->collectSubagentTokenUsage($state);

        echo "DEBUG: DETAILED USAGE ANALYSIS\n";
        echo str_repeat("=", 68) . "\n\n";

        // Analyze each step
        echo "Main Agent Steps Breakdown:\n";
        foreach ($state->steps() as $idx => $step) {
            echo "  Step " . ($idx + 1) . ":\n";
            echo "    Input messages: " . $step->inputMessages()->count() . "\n";
            echo "    Output messages: " . $step->outputMessages()->count() . "\n";

            // Show actual message content (truncated)
            foreach ($step->inputMessages()->all() as $msgIdx => $msg) {
                $content = $msg->toString();
                $preview = substr($content, 0, 150);
                echo "      Input msg {$msgIdx}: " . strlen($content) . " chars - " . str_replace("\n", " ", $preview) . "...\n";
            }

            foreach ($step->outputMessages()->all() as $msgIdx => $msg) {
                $content = $msg->toString();
                $preview = substr($content, 0, 150);
                echo "      Output msg {$msgIdx}: " . strlen($content) . " chars - " . str_replace("\n", " ", $preview) . "...\n";
            }
        }

        echo "\n" . str_repeat("=", 68) . "\n\n";

        echo "CONTEXT ISOLATION DEMO:\n\n";

        // Show subagent token usage
        if (!empty($subagentTokenUsage)) {
            echo "Subagent Token Usage (isolated contexts):\n";
            foreach ($subagentTokenUsage as $idx => $usage) {
                $num = $idx + 1;
                echo "  Subagent {$num}: {$usage['input']} input + {$usage['output']} output = {$usage['total']} tokens\n";
            }

            $totalSubagentTokens = array_sum(array_column($subagentTokenUsage, 'total'));
            echo "  Total subagent tokens: {$totalSubagentTokens}\n\n";
        }

        // Show main agent token usage
        echo "Main Agent Token Usage (coordination only):\n";
        echo "  Main agent: {$state->usage()->inputTokens} input + {$state->usage()->outputTokens} output = {$mainAgentTokens} tokens\n\n";

        // Show the value
        echo "VALUE DEMONSTRATION:\n";
        echo "  ✓ Detailed analysis stayed in isolated subagent contexts\n";
        echo "  ✓ Main agent only processed summaries, not full reviews\n";
        echo "  ✓ Main agent context stays clean and focused\n";
        echo "  ✓ Scales to 10, 50, 100 files without context explosion\n\n";
    }

    private function collectSubagentTokenUsage(AgentState $state): array {
        $subagentUsage = [];

        // Get subagent states from static storage (not from tool results!)
        $subagentStates = SpawnSubagentTool::getSubagentStates();

        foreach ($subagentStates as $entry) {
            $subagentState = $entry['state'];
            $usage = $subagentState->usage();

            $subagentUsage[] = [
                'name' => $entry['name'],
                'input' => $usage->inputTokens,
                'output' => $usage->outputTokens,
                'total' => $usage->total(),
            ];
        }

        return $subagentUsage;
    }

    private function printStats(AgentState $state): void {
        $usage = $state->usage();
        echo "Stats:\n";
        echo "  Steps: {$state->stepCount()}\n";
        echo "  Status: {$state->status()->value}\n";
        echo "  Total tokens: {$usage->total()}\n";
    }
}

// =============================================================================
// ACTION: MULTI-FILE REVIEW
// =============================================================================

final class MultiFileReviewAction
{
    private Agent $agent;
    private ReviewProgressPrinter $progressPrinter;
    private ReviewResultPrinter $resultPrinter;

    public function __construct(
        private string $workDir,
        private ?string $llmPreset = null,
    ) {
        $this->setup();
    }

    private function setup(): void {
        $registry = (new QuickReviewAgentRegistry())->create();
        $subagentPolicy = new SubagentPolicy(maxDepth: 3, summaryMaxChars: 8000);

        $builder = AgentBuilder::base()
            ->withCapability(new UseBash())
            ->withCapability(new UseFileTools($this->workDir))
            ->withCapability(new UseTaskPlanning())
            ->withCapability(new UseSubagents($registry, $subagentPolicy))
            ->withMaxSteps(20);
        if ($this->llmPreset) {
            $builder = $builder->withLlmPreset($this->llmPreset);
        }
        $this->agent = $builder->build();

        $this->progressPrinter = new ReviewProgressPrinter();
        $this->resultPrinter = new ReviewResultPrinter();
    }

    public function __invoke(array $files): AgentState {
        $task = $this->buildTask($files);
        $state = $this->executeReview($task);
        ($this->resultPrinter)($state);

        return $state;
    }

    private function buildTask(array $files): string {
        $fileCount = count($files);
        $fileList = implode("\n", array_map(fn($f) => "- {$f}", $files));

        return <<<TASK
Review these {$fileCount} classes for code quality:
{$fileList}

For EACH file:
1. Use the 'quick-reviewer' subagent
2. Get ONE specific finding per class
3. Keep subagent review focused and fast

After all reviews, provide:
- Summary of all findings
- Top priority issue across all files

Important: Each subagent review is isolated - detailed analysis
stays in subagent context, you only coordinate the summaries.
TASK;
    }

    private function executeReview(string $task): AgentState {
        // Clear any previous subagent states
        SpawnSubagentTool::clearSubagentStates();

        $state = AgentState::empty()->withMessages(
            Messages::fromString($task)
        );

        while ($this->agent->hasNextStep($state)) {
            $state = $this->agent->nextStep($state);
            ($this->progressPrinter)($state->currentStep());
        }

        // Ensure completion - force final steps if still in progress
        $safetyLimit = 5;
        $attempts = 0;
        while ($state->status() === AgentStatus::InProgress && $attempts < $safetyLimit) {
            $state = $this->agent->nextStep($state);
            ($this->progressPrinter)($state->currentStep());
            $attempts++;
        }

        return $state;
    }
}

// =============================================================================
// MAIN
// =============================================================================

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    Subagent Demo: Context Isolation & Coordination            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$llmPreset = $argv[1] ?? 'anthropic';

echo "Value Proposition:\n";
echo "  • Main agent coordinates multiple reviews\n";
echo "  • Each review runs in isolated subagent context\n";
echo "  • Detailed analysis doesn't pollute main agent\n";
echo "  • Scales to many files without context explosion\n\n";

echo str_repeat("─", 68) . "\n\n";

// Small, fast files for quick demo
$filesToReview = [
    'packages/addons/src/Agent/Agents/AgentSpec.php',
    'packages/addons/src/Agent/Agents/AgentRegistry.php',
    'packages/addons/src/Agent/Agents/AgentSpecParser.php',
];

echo "Reviewing " . count($filesToReview) . " classes:\n";
foreach ($filesToReview as $file) {
    echo "  • " . basename($file) . "\n";
}
echo "\n";

$projectRoot = dirname(__DIR__, 3);

$action = new MultiFileReviewAction(
    workDir: $projectRoot,
    llmPreset: $llmPreset
);

echo "Executing (each subagent review is fast & isolated)...\n\n";

$action($filesToReview);

echo "\n✓ Demo complete\n";
