<?php

require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\Extras\SelfCritique\SelfCriticContinuationCheck;
use Cognesy\Addons\Agent\Extras\SelfCritique\SelfCriticProcessor;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Tools\File\SearchFilesTool;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendContextMetadata;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Messages\Messages;

/**
 * Agent Self-Critic Example
 *
 * Demonstrates automatic self-criticism using SelfCriticProcessor.
 * The processor evaluates each final response against the original task
 * and requests revisions if the answer is incomplete or incorrect.
 *
 * This example asks about the testing framework - a question where the agent
 * might initially guess "PHPUnit" (common in PHP) but should discover the
 * project actually uses Pest after proper investigation.
 *
 * The self-critic catches incomplete or incorrect answers and forces
 * the agent to dig deeper before accepting the response.
 *
 * Usage:
 *   php run.php [preset]
 */

// =============================================================================
// STEP PRINTER
// =============================================================================

final class SelfCriticStepPrinter
{
    private int $stepNum = 0;

    public function __invoke(AgentStep $step): void {
        $this->stepNum++;
        $stepType = $step->stepType()->value;

        print("Step {$this->stepNum}: [{$stepType}]\n");

        $this->printErrors($step);
        $this->printToolCalls($step);
        $this->printToolExecutions($step);
        $this->printResponseStatus($step);
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
                'search_files' => "pattern={$args['pattern']}",
                'read_file' => "path=" . ($args['path'] ?? ''),
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
                print("    ✗ {$name}: ERROR\n");
            } else {
                $value = $execution->value();
                $preview = is_string($value) ? substr($value, 0, 120) : 'OK';
                if (strlen($value) > 120) {
                    $preview .= '...';
                }
                $preview = str_replace("\n", " ", $preview);
                print("    ✓ {$name}: {$preview}\n");
            }
        }
    }

    private function printResponseStatus(AgentStep $step): void {
        if (!$step->hasToolCalls() && $step->outputMessages()->count() > 0) {
            print("  → Response generated (evaluating...)\n");
        }
    }
}

// =============================================================================
// RESULT PRINTER
// =============================================================================

final class SelfCriticResultPrinter
{
    public function __invoke(AgentState $state, SelfCriticProcessor $processor): void {
        print("\n");
        $this->printAnswer($state);
        $this->printCriticResult($processor);
        $this->printStats($state);
        $this->printHintIfNeeded($state);
    }

    private function printAnswer(AgentState $state): void {
        $response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

        print("Final Answer:\n");
        print(str_repeat("═", 68) . "\n");
        print($response . "\n");
        print(str_repeat("═", 68) . "\n\n");
    }

    private function printCriticResult(SelfCriticProcessor $processor): void {
        $criticResult = $processor->lastResult();
        if (!$criticResult) {
            return;
        }

        print("Self-Critic Evaluation:\n");
        print("  Status: " . ($criticResult->approved ? "✓ APPROVED" : "✗ NOT APPROVED") . "\n");
        print("  Summary: {$criticResult->summary}\n");

        if (!empty($criticResult->strengths)) {
            print("  Strengths:\n");
            foreach ($criticResult->strengths as $strength) {
                print("    + {$strength}\n");
            }
        }

        if (!empty($criticResult->weaknesses)) {
            print("  Weaknesses:\n");
            foreach ($criticResult->weaknesses as $weakness) {
                print("    - {$weakness}\n");
            }
        }

        print("  Iterations: {$processor->iterationCount()}\n");
    }

    private function printStats(AgentState $state): void {
        $usage = $state->usage();

        print("\nStats:\n");
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

final class SelfCriticAgentBuilder
{
    public function __invoke(string $projectRoot, ?string $llmPreset): array {
        $tools = new Tools(
            SearchFilesTool::inDirectory($projectRoot),
            ReadFileTool::inDirectory($projectRoot),
        );

        $continuationCriteria = ContinuationCriteria::all(
            new StepsLimit(15, fn($s) => $s->stepCount()),
            new TokenUsageLimit(32768, fn($s) => $s->usage()->total()),
            ContinuationCriteria::any(
                new ToolCallPresenceCheck(
                    fn($s) => $s->stepCount() === 0 || ($s->currentStep()?->hasToolCalls() ?? false)
                ),
                new SelfCriticContinuationCheck(maxIterations: 3),
            ),
        );

        $selfCriticProcessor = new SelfCriticProcessor(
            maxIterations: 3,
            verbose: true,
            llmPreset: $llmPreset,
        );

        $builder = AgentBuilder::new()->withTools($tools);
        if ($llmPreset) {
            $builder = $builder->withLlmPreset($llmPreset);
        }
        $agent = $builder->build()
            ->withContinuationCriteria(...$continuationCriteria->all())
            ->withProcessors(
                new AccumulateTokenUsage(),
                new AppendContextMetadata(),
                new AppendStepMessages(),
                $selfCriticProcessor,
            );

        return [$agent, $selfCriticProcessor];
    }
}

// =============================================================================
// RUNNER
// =============================================================================

final class SelfCriticAgentRunner
{
    private Agent $agent;
    private SelfCriticProcessor $selfCriticProcessor;
    private SelfCriticStepPrinter $stepPrinter;
    private SelfCriticResultPrinter $resultPrinter;

    public function __construct(
        private string $projectRoot,
        private ?string $llmPreset = null,
    ) {
        [$this->agent, $this->selfCriticProcessor] = (new SelfCriticAgentBuilder())(
            $this->projectRoot,
            $this->llmPreset
        );
        $this->stepPrinter = new SelfCriticStepPrinter();
        $this->resultPrinter = new SelfCriticResultPrinter();
    }

    public function __invoke(string $question): string {
        $state = AgentState::empty()->withMessages(
            Messages::fromString($question)
        );

        while ($this->agent->hasNextStep($state)) {
            $state = $this->agent->nextStep($state);
            ($this->stepPrinter)($state->currentStep());
        }

        ($this->resultPrinter)($state, $this->selfCriticProcessor);

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
        print("║          Agent - Self-Critic Pattern Demo                      ║\n");
        print("╚════════════════════════════════════════════════════════════════╝\n\n");

        if ($llmPreset) {
            print("Using LLM preset: {$llmPreset}\n\n");
        }

        print("Question:\n");
        print(str_repeat("─", 68) . "\n");
        print($question . "\n");
        print(str_repeat("─", 68) . "\n\n");
        print("Processing with automatic self-criticism...\n\n");
    }
}

// =============================================================================
// MAIN
// =============================================================================

$llmPreset = $argv[1] ?? null;
$projectRoot = dirname(__DIR__, 3);

$question = <<<QUESTION
What testing framework does this project use? Explain how you plan to determine the answer, then find and read the relevant files to provide a complete and accurate response.
QUESTION;

$headerPrinter = new HeaderPrinter();
$headerPrinter($llmPreset, $question);

$runner = new SelfCriticAgentRunner(projectRoot: $projectRoot, llmPreset: $llmPreset);
$runner($question);
