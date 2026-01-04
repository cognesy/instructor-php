<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentFactory;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Messages\Messages;

/**
 * Agent Basic Example
 *
 * Demonstrates the simplest use of Agent - a trivial Q&A without tools.
 * The agent uses the LLM directly to answer a simple question.
 */

print("╔════════════════════════════════════════════════════════════════╗\n");
print("║                  Agent - Basic Q&A Demo                        ║\n");
print("╚════════════════════════════════════════════════════════════════╝\n\n");

// Create a basic agent (no tools needed for simple Q&A)
$agent = AgentFactory::default();

// Initialize state with user question
$state = AgentState::empty()->withMessages(
    Messages::fromString('What is the capital of France? Answer in one sentence.')
);

print("Question: What is the capital of France?\n\n");
print("Processing...\n\n");

// Run agent to completion
$finalState = $agent->finalStep($state);

// Extract and display the response
$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';

print("Answer:\n");
print(str_repeat("─", 68) . "\n");
print($response . "\n");
print(str_repeat("─", 68) . "\n\n");

// Display stats
print("Stats:\n");
print("  Steps: {$finalState->stepCount()}\n");
print("  Status: {$finalState->status()->value}\n");
$usage = $finalState->usage();
print("  Tokens: {$usage->inputTokens} input, {$usage->outputTokens} output\n");
