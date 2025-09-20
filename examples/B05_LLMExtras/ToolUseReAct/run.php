---
title: 'Inference and tool use'
docname: 'tool_use'
---

## Overview

### Example
```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\Core\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\Core\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Drivers\ReAct\StopOnFinalDecision;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

function add_numbers(int $a, int $b) : int { return $a + $b; }
function subtract_numbers(int $a, int $b) : int { return $a - $b; }

$driver = new ReActDriver(
    llm: LLMProvider::using('openai'),
    finalViaInference: true,
);

$toolUse = ToolUseFactory::default(
    tools: (new Tools)->withTools(
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...))
    ),
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(6, fn(ToolUseState $state) => $state->stepCount()),
        new TokenUsageLimit(8192, fn(ToolUseState $state) => $state->usage()->total()),
        new ExecutionTimeLimit(60, fn(ToolUseState $state) => $state->startedAt()),
        new RetryLimit(2, fn(ToolUseState $state) => $state->steps(), fn(ToolUseStep $step) => $step->hasErrors()),
        new StopOnFinalDecision(),
    ),
    driver: $driver
);


//
// PATTERN #1 - manual control
//
echo "\nReAct PATTERN #1 - manual control\n";
$state = (new ToolUseState)
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

while ($toolUse->hasNextStep($state)) {
    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();
    print("STEP - tokens used: " . ($step->usage()?->total() ?? 0)  . ' [' . $step->toString() . ']' . "\n");
}

$result = $state->currentStep()->outputMessages()->toString();
print("RESULT: " . $result . "\n");


//
// PATTERN #2 - using iterator
//
echo "\nReAct PATTERN #2 - using iterator\n";
$state = (new ToolUseState)
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

foreach ($toolUse->iterator($state) as $currentState) {
    $step = $currentState->currentStep();
    print("STEP - tokens used: " . ($step->usage()?->total() ?? 0)  . ' [' . $step->toString() . ']' . "\n");
    $state = $currentState; // keep the latest state
}

$result = $state->currentStep()->outputMessages()->toString();
print("RESULT: " . $result . "\n");


//
// PATTERN #3 - just get final step (fast forward to it)
//
echo "\nReAct PATTERN #3 - final via Inference (optional)\n";
$state = (new ToolUseState)
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

$finalState = $toolUse->finalStep($state);
$result = $finalState->currentStep()->outputMessages()->toString();
print("RESULT: " . $result . "\n");

?>
```
