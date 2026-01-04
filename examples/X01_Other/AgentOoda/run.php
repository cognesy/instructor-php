<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Ooda\OodaDriver;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Tools\File\SearchFilesTool;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\Agent\Continuation\ToolCallPresenceCheck;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

/**
 * OODA Loop Agent Example
 *
 * Demonstrates an agent that follows the OODA decision cycle:
 * - OBSERVE: Assess current state, goal, previous results, obstacles
 * - ORIENT: Analyze situation, identify options, evaluate alternatives
 * - DECIDE: Choose the best action (tool call or final answer)
 * - ACT: Execute the chosen action
 *
 * The structured reasoning makes the agent's decision-making transparent
 * and allows for explicit tracking of progress toward the goal.
 *
 * Usage:
 *   php run.php [preset]
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║          Agent - OODA Loop Pattern Demo                       ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

$llmPreset = $argv[1] ?? null;

if ($llmPreset) {
    print("Using LLM preset: {$llmPreset}\n\n");
}

$projectRoot = dirname(__DIR__, 3);

// Tools for file exploration
$tools = new Tools(
    SearchFilesTool::inDirectory($projectRoot),
    ReadFileTool::inDirectory($projectRoot),
);

// OODA driver with verbose logging
$driver = new OodaDriver(
    llm: $llmPreset ? LLMProvider::fromPreset($llmPreset) : null,
    verbose: true,
);

// Continuation criteria
$continuationCriteria = ContinuationCriteria::all(
    new StepsLimit(10, fn($s) => $s->stepCount()),
    new TokenUsageLimit(32768, fn($s) => $s->usage()->total()),
    new ToolCallPresenceCheck(
        fn($s) => $s->stepCount() === 0 || ($s->currentStep()?->hasToolCalls() ?? false)
    ),
);

// Build agent with OODA driver
$agent = AgentFactory::default(
    tools: $tools,
    continuationCriteria: $continuationCriteria,
    driver: $driver,
);

// Task that requires investigation and reasoning
$question = <<<QUESTION
What testing framework does this project use? Think like a senior developer verifying unfamiliar code - ask yourself before using tools: 'What is the source of truth?'. Prepare a research plan and follow it. Be persistent, continue correcting the plan until you are certain of the answer. Early impressions might be misleading. Be self-critical and verify your findings carefully. Do not stop until you have high confidence in the final answer.
QUESTION;

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

print("Task:\n");
print(str_repeat("─", 68) . "\n");
print($question . "\n");
print(str_repeat("─", 68) . "\n\n");
print("Processing with OODA loop...\n");

// Run agent step by step
$stepNum = 0;
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
    $step = $state->currentStep();
    $stepNum++;

    $stepType = $step->stepType()->value;
    print("\nStep {$stepNum}: [{$stepType}]\n");

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
                'search_files' => isset($args['patterns'])
                    ? "patterns=[" . implode(', ', $args['patterns']) . "]"
                    : "pattern=" . ($args['pattern'] ?? json_encode($args)),
                'read_file' => "path=" . ($args['path'] ?? ''),
                default => json_encode($args),
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
                $preview = is_string($value) ? substr($value, 0, 80) : 'OK';
                if (strlen($value) > 80) $preview .= '...';
                $preview = str_replace("\n", " ", $preview);
                print("    ✓ {$name}: {$preview}\n");
            }
        }
    }
}

// Display final answer
$response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

print("\n");
print("Final Answer:\n");
print(str_repeat("═", 68) . "\n");
print($response . "\n");
print(str_repeat("═", 68) . "\n\n");

print("Stats:\n");
print("  Steps: {$state->stepCount()}\n");
print("  Status: {$state->status()->value}\n");
$usage = $state->usage();
print("  Tokens: {$usage->inputTokens} input, {$usage->outputTokens} output\n");
