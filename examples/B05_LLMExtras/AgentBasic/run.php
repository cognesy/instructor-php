<?php

require 'examples/boot.php';

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Messages\Messages;

/**
 * Agent Basic Example
 *
 * Demonstrates the simplest use of Agent - a trivial Q&A without tools.
 * The agent uses the LLM directly to answer a simple question.
 *
 * Usage:
 *   php run.php [preset]
 *
 * Examples:
 *   php run.php              # Uses default LLM connection
 *   php run.php openai       # Uses OpenAI preset
 *   php run.php anthropic    # Uses Anthropic preset
 */

// =============================================================================
// RESULT PRINTER
// =============================================================================

final class ResultPrinter
{
    public function __invoke(AgentState $state): void {
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
            print("      Check your API key configuration in /config/llm.php or try:\n");
            print("      php run.php openai    # If you have OPENAI_API_KEY set\n");
            print("      php run.php anthropic # If you have ANTHROPIC_API_KEY set\n");
        }
    }
}

// =============================================================================
// RUNNER
// =============================================================================

final class BasicAgentRunner
{
    private Agent $agent;
    private ResultPrinter $printer;

    public function __construct(
        private ?string $llmPreset = null,
    ) {
        $builder = AgentBuilder::new();
        if ($this->llmPreset) {
            $builder = $builder->withLlmPreset($this->llmPreset);
        }
        $this->agent = $builder->build();
        $this->printer = new ResultPrinter();
    }

    public function __invoke(string $question): string {
        $state = AgentState::empty()->withMessages(
            Messages::fromString($question)
        );

        $finalState = $this->agent->finalStep($state);

        ($this->printer)($finalState);

        return $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';
    }
}

// =============================================================================
// HEADER PRINTER
// =============================================================================

final class HeaderPrinter
{
    public function __invoke(?string $llmPreset, string $question): void {
        print("╔════════════════════════════════════════════════════════════════╗\n");
        print("║                  Agent - Basic Q&A Demo                        ║\n");
        print("╚════════════════════════════════════════════════════════════════╝\n\n");

        if ($llmPreset) {
            print("Using LLM preset: {$llmPreset}\n\n");
        }

        print("Question: {$question}\n\n");
        print("Processing...\n\n");
    }
}

// =============================================================================
// MAIN
// =============================================================================

$llmPreset = $argv[1] ?? null;
$question = 'What is the capital of France? Answer in one sentence.';

$headerPrinter = new HeaderPrinter();
$headerPrinter($llmPreset, $question);

$runner = new BasicAgentRunner(llmPreset: $llmPreset);
$runner($question);
