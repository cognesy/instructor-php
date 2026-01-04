<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

/**
 * Agent File System Example
 *
 * Demonstrates using the Agent with file tools to read and analyze files.
 * The agent reads composer.json and answers questions about the project.
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║              Agent - File System Access Demo                   ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

// Get project root directory
$projectRoot = dirname(__DIR__, 3);

// Create agent with file tools scoped to project root
$agent = AgentFactory::withFileTools(baseDir: $projectRoot);

// Initialize state with user question about the project
$state = AgentState::empty()->withMessages(
    Messages::fromString(
        "Read the composer.json file in the current directory and tell me:\n" .
        "1. What is the project name?\n" .
        "2. What PHP version is required?\n" .
        "3. List the first 5 dependencies (require section only).\n" .
        "Be concise."
    )
);

print("Task: Analyze composer.json and extract project information\n\n");
print("Processing...\n\n");

// Run agent step by step to show progress
$stepNum = 0;
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);
    $step = $state->currentStep();
    $stepNum++;

    $stepType = $step->stepType()->value;
    $hasTools = $step->hasToolCalls() ? 'yes' : 'no';

    print("Step {$stepNum}: [{$stepType}] tools_called={$hasTools}\n");

    // Show tool calls if any
    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            print("  → {$toolCall->name()}()\n");
        }
    }
}

print("\n");

// Extract and display the response
$response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

print("Answer:\n");
print(str_repeat("─", 68) . "\n");
print($response . "\n");
print(str_repeat("─", 68) . "\n\n");

// Display stats
print("Stats:\n");
print("  Steps: {$state->stepCount()}\n");
print("  Status: {$state->status()->value}\n");
$usage = $state->usage();
print("  Tokens: {$usage->inputTokens} input, {$usage->outputTokens} output\n");
