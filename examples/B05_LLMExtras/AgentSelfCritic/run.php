<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Continuation\SelfCriticContinuationCheck;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Enums\AgentStatus;
use Cognesy\Addons\Agent\StateProcessors\SelfCriticProcessor;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Tools\File\SearchFilesTool;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
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

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║          Agent - Self-Critic Pattern Demo                      ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

$llmPreset = $argv[1] ?? null;

if ($llmPreset) {
    print("Using LLM preset: {$llmPreset}\n\n");
}

$projectRoot = dirname(__DIR__, 3);

// Tools for file search and reading
$tools = new Tools(
    SearchFilesTool::inDirectory($projectRoot),
    ReadFileTool::inDirectory($projectRoot),
);

// Continuation criteria with composable logic:
// - ALL limits must pass (step limit, token limit)
// - ANY continuation trigger can keep it going (tool calls OR self-critic not approved)
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

// SelfCriticProcessor evaluates responses using Instructor for structured output
// It stores the result in metadata, which SelfCriticContinuationCheck reads
$selfCriticProcessor = new SelfCriticProcessor(
    maxIterations: 3,
    verbose: true,
    llmPreset: $llmPreset,
);

// Build agent with self-critic processor
$agent = AgentFactory::default(
    tools: $tools,
    llmPreset: $llmPreset,
    continuationCriteria: $continuationCriteria,
)->withProcessors(
    new AccumulateTokenUsage(),
    new AppendContextMetadata(),
    new AppendStepMessages(),
    $selfCriticProcessor,
);

// Question that requires checking a specific file
// The self-critic will catch if the agent gives an answer without reading the file
$question = <<<QUESTION
What testing framework does this project use? Explain how you plan to determine the answer, then find and read the relevant files to provide a complete and accurate response.
QUESTION;

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

print("Question:\n");
print(str_repeat("─", 68) . "\n");
print($question . "\n");
print(str_repeat("─", 68) . "\n\n");
print("Processing with automatic self-criticism...\n\n");

// Run agent step by step
$stepNum = 0;
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
    $step = $state->currentStep();
    $stepNum++;

    $stepType = $step->stepType()->value;
    print("Step {$stepNum}: [{$stepType}]\n");

    // Show errors
    if ($step->hasErrors()) {
        foreach ($step->errors() as $error) {
            print("  ⚠ Error: " . $error->getMessage() . "\n");
        }
    }

    // Show tool calls
    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            $args = $toolCall->args();
            $argStr = match($toolCall->name()) {
                'search_files' => "pattern={$args['pattern']}",
                'read_file' => "path=" . ($args['path'] ?? ''),
                default => '',
            };
            print("  → {$toolCall->name()}({$argStr})\n");
        }
    }

    // Show tool execution results
    if ($step->toolExecutions()->hasExecutions()) {
        foreach ($step->toolExecutions()->all() as $execution) {
            $name = $execution->name();
            if ($execution->hasError()) {
                print("    ✗ {$name}: ERROR\n");
            } else {
                $value = $execution->value();
                $preview = is_string($value) ? substr($value, 0, 120) : 'OK';
                if (strlen($value) > 120) $preview .= '...';
                $preview = str_replace("\n", " ", $preview);
                print("    ✓ {$name}: {$preview}\n");
            }
        }
    }

    // Show response status
    if (!$step->hasToolCalls() && $step->outputMessages()->count() > 0) {
        print("  → Response generated (evaluating...)\n");
    }
}

// Display final answer
$response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

print("\n");
print("Final Answer:\n");
print(str_repeat("═", 68) . "\n");
print($response . "\n");
print(str_repeat("═", 68) . "\n\n");

// Display self-critic summary
$criticResult = $selfCriticProcessor->lastResult();
if ($criticResult) {
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
    print("  Iterations: {$selfCriticProcessor->iterationCount()}\n");
}

print("\nStats:\n");
print("  Steps: {$state->stepCount()}\n");
print("  Status: {$state->status()->value}\n");
$usage = $state->usage();
print("  Tokens: {$usage->inputTokens} input, {$usage->outputTokens} output\n");

if ($state->status() === AgentStatus::Failed && $usage->total() === 0) {
    print("\nHint: Status 'failed' with 0 tokens usually means the LLM connection failed.\n");
    print("      Try: php run.php openai    # If you have OPENAI_API_KEY set\n");
}
