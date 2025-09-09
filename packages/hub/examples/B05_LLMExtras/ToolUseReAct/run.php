<?php
require 'examples/boot.php';

use Cognesy\Addons\ToolUse\ContinuationCriteria\{ExecutionTimeLimit, RetryLimit, StepsLimit, TokenUsageLimit};
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Drivers\ReAct\StopOnFinalDecision;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Polyglot\Inference\LLMProvider;

function add_numbers(int $a, int $b) : int { return $a + $b; }
function subtract_numbers(int $a, int $b) : int { return $a - $b; }

$criteria = new ContinuationCriteria(
    new StepsLimit(6),
    new TokenUsageLimit(8192),
    new ExecutionTimeLimit(60),
    new RetryLimit(2),
    new StopOnFinalDecision(),
);

$driver = new ReActDriver(
    llm: LLMProvider::using('openai'),
    finalViaInference: true,
);

//
// PATTERN #1 - manual control
//
echo "\nReAct PATTERN #1 - manual control\n";
$tools = (new \Cognesy\Addons\ToolUse\Tools)
    ->withTool(FunctionTool::fromCallable(add_numbers(...)))
    ->withTool(FunctionTool::fromCallable(subtract_numbers(...)));

$state = (new \Cognesy\Addons\ToolUse\Data\ToolUseState)
    ->withMessages(\Cognesy\Messages\Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

$toolUse = new ToolUse(
    tools: $tools,
    continuationCriteria: $criteria,
    driver: $driver
);

while ($toolUse->hasNextStep($state)) {
    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();
    print("STEP - tokens used: " . ($step->usage()?->total() ?? 0) . "\n");
}

$result = $state->currentStep()->response();
print("RESULT: " . $result . "\n");


//
// PATTERN #2 - using iterator
//
echo "\nReAct PATTERN #2 - using iterator\n";
$tools = (new \Cognesy\Addons\ToolUse\Tools)
    ->withTool(FunctionTool::fromCallable(add_numbers(...)))
    ->withTool(FunctionTool::fromCallable(subtract_numbers(...)));

$state = (new \Cognesy\Addons\ToolUse\Data\ToolUseState)
    ->withMessages(\Cognesy\Messages\Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

$toolUse = new ToolUse(
    tools: $tools,
    continuationCriteria: $criteria,
    driver: $driver
);

foreach ($toolUse->iterator($state) as $currentState) {
    $step = $currentState->currentStep();
    print("STEP - tokens used: " . ($step->usage()?->total() ?? 0) . "\n");
    $state = $currentState; // keep the latest state
}

$result = $state->currentStep()->response();
print("RESULT: " . $result . "\n");


//
// PATTERN #3 - just get final step (fast forward to it)
//
echo "\nReAct PATTERN #3 - final via Inference (optional)\n";
$tools = (new \Cognesy\Addons\ToolUse\Tools)
    ->withTool(FunctionTool::fromCallable(add_numbers(...)))
    ->withTool(FunctionTool::fromCallable(subtract_numbers(...)));

$state = (new \Cognesy\Addons\ToolUse\Data\ToolUseState)
    ->withMessages(\Cognesy\Messages\Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

$toolUse = new ToolUse(
    tools: $tools,
    continuationCriteria: $criteria,
    driver: $driver
);

$finalState = $toolUse->finalStep($state);
$result = $finalState->currentStep()->response();
print("RESULT: " . $result . "\n");
