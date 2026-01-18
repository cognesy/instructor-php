<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Addons\Agent\Registry\AgentSpec;
use Cognesy\Messages\Messages;

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Create subagent registry
$registry = new AgentRegistry();

// Register code reviewer subagent
$registry->register(new AgentSpec(
    name: 'reviewer',
    description: 'Reviews code files and identifies issues',
    systemPrompt: 'You review code files and identify issues. Read the file and provide a concise assessment focusing on code quality, potential bugs, and improvements.',
    tools: ['read_file'],
));

// Register documentation generator subagent
$registry->register(new AgentSpec(
    name: 'documenter',
    description: 'Generates documentation for code',
    systemPrompt: 'You generate documentation for code. Read the file and create brief, clear documentation explaining what the code does and how to use it.',
    tools: ['read_file'],
));

// Build main orchestration agent
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

// Task requiring multiple isolated reviews
$task = <<<TASK
Review these three files and provide a summary:
1. src/Agent/AgentBuilder.php
2. src/Agent/AgentState.php
3. src/Agent/Agent.php

For each file, spawn a reviewer subagent. Then summarize the findings.
TASK;

$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

// Execute with subagent spawning
echo "Task: Review multiple files\n\n";

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    // we can introspect agent step state here
    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            if ($toolCall->name() === 'spawn_subagent') {
                $args = $toolCall->args();
                echo "  → spawn_subagent(subagent={$args['subagent']}, prompt=...)\n";
            } else {
                echo "  → {$toolCall->name()}()\n";
            }
        }
    }
}

// Extract summary
$summary = $state->currentStep()?->outputMessages()->toString() ?? 'No summary';

echo "\nSummary:\n";
echo $summary . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Subagents spawned: " . ($state->metadata()->get('subagent_count') ?? 0) . "\n";
echo "  Status: {$state->status()->value}\n";
?>
