<?php
require 'examples/boot.php';

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\File\UseFileTools;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Messages\Messages;

// Configure working directory (security boundary)
$workDir = dirname(__DIR__, 3);  // Project root

// Build agent with file system capabilities
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools($workDir))
    ->build();

// Create task that requires file access
$task = <<<TASK
Read the composer.json file and tell me:
1. What is the project name?
2. What PHP version is required?
3. List the first 5 dependencies (require section only).
Be concise.
TASK;

// Execute with initial state
$state = AgentState::empty()->withMessages(
    Messages::fromString($task)
);

// Run agent loop
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

// Extract final response
$response = $state->currentStep()?->outputMessages()->toString() ?? 'No response';

echo "\nAnswer:\n";
echo $response . "\n\n";

echo "Stats:\n";
echo "  Steps: {$state->stepCount()}\n";
echo "  Status: {$state->status()->value}\n";
?>
