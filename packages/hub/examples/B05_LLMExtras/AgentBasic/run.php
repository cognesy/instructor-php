<?php declare(strict_types=1);
require 'examples/boot.php';

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
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

if ($finalState->status() === AgentStatus::Failed) {
    $debug = $finalState->debug();
    $stepType = $finalState->currentStep()?->stepType()?->value;
    if ($stepType !== null) {
        echo "Step type: {$stepType}\n";
    }
    if (($debug['stopReason'] ?? '') !== '') {
        echo "Stop reason: {$debug['stopReason']}\n";
    }
    if (($debug['resolvedBy'] ?? '') !== '') {
        echo "Resolved by: {$debug['resolvedBy']}\n";
    }
    if (($debug['errors'] ?? '') !== '') {
        echo "Errors: {$debug['errors']}\n";
    }
}
?>
