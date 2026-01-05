<?php

require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Messages\Messages;

/**
 * Agent File System Example
 *
 * Demonstrates using the Agent with file tools to read and analyze files.
 * The agent reads composer.json and answers questions about the project.
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

final class StepPrinter
{
    private int $stepNum = 0;

    public function __invoke(AgentStep $step): void {
        $this->stepNum++;
        $stepType = $step->stepType()->value;
        $hasTools = $step->hasToolCalls() ? 'yes' : 'no';

        print("Step {$this->stepNum}: [{$stepType}] tools_called={$hasTools}\n");

        if ($step->hasToolCalls()) {
            foreach ($step->toolCalls()->all() as $toolCall) {
                print("  → {$toolCall->name()}()\n");
            }
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
// RUNNER
// =============================================================================

final class FileSystemAgentRunner
{
    private Agent $agent;
    private StepPrinter $stepPrinter;
    private ResultPrinter $resultPrinter;

    public function __construct(
        private string $workDir,
        private ?string $llmPreset = null,
    ) {
        $builder = AgentBuilder::new()->withFileTools($this->workDir);
        if ($this->llmPreset) {
            $builder = $builder->withLlmPreset($this->llmPreset);
        }
        $this->agent = $builder->build();
        $this->stepPrinter = new StepPrinter();
        $this->resultPrinter = new ResultPrinter();
    }

    public function __invoke(string $task): string {
        $state = AgentState::empty()->withMessages(
            Messages::fromString($task)
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
    public function __invoke(?string $llmPreset): void {
        print("╔════════════════════════════════════════════════════════════════╗\n");
        print("║              Agent - File System Access Demo                   ║\n");
        print("╚════════════════════════════════════════════════════════════════╝\n\n");

        if ($llmPreset) {
            print("Using LLM preset: {$llmPreset}\n\n");
        }

        print("Task: Analyze composer.json and extract project information\n\n");
        print("Processing...\n\n");
    }
}

// =============================================================================
// MAIN
// =============================================================================

$llmPreset = $argv[1] ?? null;
$projectRoot = dirname(__DIR__, 3);

$task = <<<TASK
Read the composer.json file in the current directory and tell me:
1. What is the project name?
2. What PHP version is required?
3. List the first 5 dependencies (require section only).
Be concise.
TASK;

$headerPrinter = new HeaderPrinter();
$headerPrinter($llmPreset);

$runner = new FileSystemAgentRunner(workDir: $projectRoot, llmPreset: $llmPreset);
$runner($task);
