<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Messages\Messages;

// Build a basic agent
$agent = AgentBuilder::new()
    ->withLlmPreset('anthropic')  // Optional: specify LLM
    ->build();

// Create initial state with user question
$state = AgentState::empty()->withMessages(
    Messages::fromString('What is the capital of France? Answer in one sentence.')
);

// Execute agent until completion
$finalState = $agent->finalStep($state);

// Extract response
$response = $finalState->currentStep()?->outputMessages()->toString() ?? 'No response';

echo "Answer: {$response}\n";
echo "Steps: {$finalState->stepCount()}\n";
echo "Status: {$finalState->status()->value}\n";
?>
