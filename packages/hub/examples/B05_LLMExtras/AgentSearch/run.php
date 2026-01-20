<?php
require 'examples/boot.php';

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Capabilities\Subagent\UseSubagents;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\AgentTemplate\Registry\AgentRegistry;
use Cognesy\Addons\AgentTemplate\Registry\AgentSpec;
use Cognesy\Messages\Messages;

// Configure working directory
$workDir = dirname(__DIR__, 3);

// Register specialized subagents
$registry = new AgentRegistry();

$registry->register(new AgentSpec(
    name: 'reader',
    description: 'Reads files and extracts relevant information',
    systemPrompt: 'You read files and extract relevant information. Be thorough and precise.',
    tools: ['read_file'],
));

$registry->register(new AgentSpec(
    name: 'searcher',
    description: 'Searches for files matching patterns',
    systemPrompt: 'You search for files matching patterns. Use glob patterns effectively.',
    tools: ['search_files'],
));

// Build main orchestration agent
$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(registry: $registry))
    ->build();

// Ask a question that requires search
$question = "Find all test files related to Agent capabilities and tell me what they test";

$state = AgentState::empty()->withMessages(
    Messages::fromString($question)
);

// Execute agent loop
while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            echo "  → {$toolCall->name()}()\n";
        }
    }
}

// Extract answer
$answer = $state->currentStep()?->outputMessages()->toString() ?? 'No answer';

echo "\nAnswer:\n";
echo $answer . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
?>
