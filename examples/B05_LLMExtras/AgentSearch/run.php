<?php

require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Agents\AgentSpec;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Capabilities\File\ReadFileTool;
use Cognesy\Addons\Agent\Capabilities\File\SearchFilesTool;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Capabilities\Subagent\SubagentPolicy;
use Cognesy\Messages\Messages;

/**
 * Agent Search Example
 *
 * Demonstrates agentic search using subagents for:
 * - Searching for files matching a pattern
 * - Reading file contents
 * - Generating a synthesized answer
 *
 * The main agent orchestrates subagents via AgentRegistry + spawn_subagent.
 *
 * Usage:
 *   php run.php [preset]
 *
 * Examples:
 *   php run.php              # Uses default LLM connection
 *   php run.php openai       # Uses OpenAI preset
 */

// =============================================================================
// STEP PRINTER
// =============================================================================

final class SearchStepPrinter
{
    private int $stepNum = 0;

    public function __invoke(AgentStep $step): void {
        $this->stepNum++;
        $stepType = $step->stepType()->value;

        print("Step {$this->stepNum}: [{$stepType}]\n");

        $this->printErrors($step);
        $this->printToolCalls($step);
        $this->printToolExecutions($step);
        $this->printFinalResponse($step);
    }

    private function printErrors(AgentStep $step): void {
        if (!$step->hasErrors()) {
            return;
        }
        foreach ($step->errors() as $error) {
            print("  ⚠ Error: " . $error->getMessage() . "\n");
        }
    }

    private function printToolCalls(AgentStep $step): void {
        if (!$step->hasToolCalls()) {
            return;
        }
        foreach ($step->toolCalls()->all() as $toolCall) {
            $args = $toolCall->args();
            $argStr = match ($toolCall->name()) {
                'search_files' => "pattern=" . ($args['pattern'] ?? ''),
                'read_file' => "path=" . ($args['path'] ?? ''),
                'spawn_subagent' => "subagent=" . ($args['subagent'] ?? ''),
                default => '',
            };
            print("  → {$toolCall->name()}({$argStr})\n");
        }
    }

    private function printToolExecutions(AgentStep $step): void {
        if (!$step->toolExecutions()->hasExecutions()) {
            return;
        }
        foreach ($step->toolExecutions()->all() as $execution) {
            $name = $execution->name();
            if ($execution->hasError()) {
                $error = $execution->error();
                print("    ✗ {$name}: ERROR - " . ($error?->getMessage() ?? 'Unknown error') . "\n");
            } else {
                $value = $execution->value();
                $preview = is_string($value) ? $value : json_encode($value);
                if (strlen($preview) > 200) {
                    $preview = substr($preview, 0, 200) . '...';
                }
                $preview = str_replace("\n", "\n      ", $preview);
                print("    ✓ {$name}: {$preview}\n");
            }
        }
    }

    private function printFinalResponse(AgentStep $step): void {
        if (!$step->hasToolCalls() && $step->outputMessages()->count() > 0) {
            print("  → Final response ready\n");
        }
    }
}

// =============================================================================
// RESULT PRINTER
// =============================================================================

final class ResultPrinter
{
    public function __invoke(AgentState $state): void {
        print("\n");
        $this->printAnswer($state);
        $this->printStats($state);
        $this->printHintIfNeeded($state);
    }

    private function printAnswer(AgentState $state): void {
        $response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

        print("Answer:\n");
        print(str_repeat("─", 68) . "\n");
        print($response . "\n");
        print(str_repeat("─", 68) . "\n\n");
    }

    private function printStats(AgentState $state): void {
        $usage = $state->usage();

        print("Stats:\n");
        print("  Steps: {$state->stepCount()}\n");
        print("  Status: {$state->status()->value}\n");
        print("  Tokens: {$usage->inputTokens} input, {$usage->outputTokens} output\n");
    }

    private function printHintIfNeeded(AgentState $state): void {
        $usage = $state->usage();

        if ($state->status() === AgentStatus::Failed && $usage->total() === 0) {
            print("\nHint: Status 'failed' with 0 tokens usually means the LLM connection failed.\n");
            print("      Try: php run.php openai    # If you have OPENAI_API_KEY set\n");
        }
    }
}

// =============================================================================
// AGENT BUILDER
// =============================================================================

final class SearchAgentBuilder
{
    public function __invoke(string $projectRoot, ?string $llmPreset): Agent {
        $searchTool = SearchFilesTool::inDirectory($projectRoot);
        $readFileTool = ReadFileTool::inDirectory($projectRoot);

        $registry = new AgentRegistry();
        $registry->register(new AgentSpec(
            name: 'file-researcher',
            description: 'Searches files and extracts evidence to answer the question',
            systemPrompt: <<<'PROMPT'
You are a file research subagent. Use search_files and read_file to gather evidence.
Prefer citing file paths and line numbers when possible. Return a concise summary.
PROMPT,
            tools: ['search_files', 'read_file'],
            model: 'inherit',
        ));

        $subagentPolicy = new SubagentPolicy(maxDepth: 2, summaryMaxChars: 8000);

        $builder = AgentBuilder::base()
            ->withTools(new Tools($searchTool, $readFileTool))
            ->withCapability(new UseSubagents($registry, $subagentPolicy))
            ->withMaxSteps(20)
            ->withMaxTokens(32768);
        if ($llmPreset) {
            $builder = $builder->withLlmPreset($llmPreset);
        }
        return $builder->build();
    }
}

// =============================================================================
// RUNNER
// =============================================================================

final class SearchAgentRunner
{
    private Agent $agent;
    private SearchStepPrinter $stepPrinter;
    private ResultPrinter $resultPrinter;

    public function __construct(
        private string $projectRoot,
        private ?string $llmPreset = null,
    ) {
        $this->agent = (new SearchAgentBuilder())($this->projectRoot, $this->llmPreset);
        $this->stepPrinter = new SearchStepPrinter();
        $this->resultPrinter = new ResultPrinter();
    }

    public function __invoke(string $question): string {
        $state = AgentState::empty()->withMessages(
            Messages::fromString($question)
        );

        while ($this->agent->hasNextStep($state)) {
            $state = $this->agent->nextStep($state);
            ($this->stepPrinter)($state->currentStep());
        }

        ($this->resultPrinter)($state);

        return $state->currentStep()?->outputMessages()->toString() ?? 'No response';
    }
}

// =============================================================================
// HEADER PRINTER
// =============================================================================

final class HeaderPrinter
{
    public function __invoke(?string $llmPreset, string $question): void {
        print("╔════════════════════════════════════════════════════════════════╗\n");
        print("║            Agent - Agentic Search with Subagents               ║\n");
        print("╚════════════════════════════════════════════════════════════════╝\n\n");

        if ($llmPreset) {
            print("Using LLM preset: {$llmPreset}\n\n");
        }

        print("Question: {$question}\n\n");
        print("Processing with subagents...\n\n");
    }
}

// =============================================================================
// MAIN
// =============================================================================

$llmPreset = $argv[1] ?? null;
$projectRoot = dirname(__DIR__, 3);
$question = <<<QUESTION
Use the "file-researcher" subagent to answer:
What testing framework does this project use? Cite evidence from the repo.
QUESTION;

$headerPrinter = new HeaderPrinter();
$headerPrinter($llmPreset, $question);

$runner = new SearchAgentRunner(projectRoot: $projectRoot, llmPreset: $llmPreset);
$runner($question);
