<?php
require 'examples/boot.php';

use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPolicyCriterion;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ContinuationCriteria\StopOnFinalDecision;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Messages;

function add_numbers(int $a, int $b) : int { return $a + $b; }
function subtract_numbers(int $a, int $b) : int { return $a - $b; }

$toolUse = ToolUseFactory::default(
    tools: new Tools(
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...))
    ),
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(6, fn(ToolUseState $state) => $state->stepCount()),
        new TokenUsageLimit(8192, fn(ToolUseState $state) => $state->usage()->total()),
        new ExecutionTimeLimit(60, fn(ToolUseState $state) => $state->startedAt()),
        ErrorPolicyCriterion::withPolicy(ErrorPolicy::retryToolErrors(2)),
        new StopOnFinalDecision(),
    ),
);

//
// PATTERN #1 - manual control
//
echo "\nPATTERN #1 - manual control\n";
$state = (new ToolUseState)
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

// iterate until no more steps
while ($toolUse->hasNextStep($state)) {
    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();
    print("STEP - tokens used: " . ($step->usage()?->total() ?? 0)  . ' [' . $step->toString() . ']' . "\n");
}

// print final response
$result = $state->currentStep()->outputMessages()->toString();
print("RESULT: " . $result . "\n");


//
// PATTERN #2 - using iterator
//
echo "\nPATTERN #2 - using iterator\n";
$state = (new ToolUseState)
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

// iterate until no more steps
foreach ($toolUse->iterator($state) as $currentState) {
    $step = $currentState->currentStep();
    print("STEP - tokens used: " . ($step->usage()?->total() ?? 0)  . ' [' . $step->toString() . ']' . "\n");
    $state = $currentState; // keep the latest state
}

// print final response
$result = $state->currentStep()->outputMessages()->toString();
print("RESULT: " . $result . "\n");



//
// PATTERN #3 - just get final step (fast forward to it)
//
echo "\nPATTERN #3 - get only final result\n";
$state = (new ToolUseState)
    ->withMessages(Messages::fromString('Add 2455 and 3558 then subtract 4344 from the result.'));

// print final response
$finalState = $toolUse->finalStep($state);
$result = $finalState->currentStep()->outputMessages()->toString();
print("RESULT: " . $result . "\n");
?>
