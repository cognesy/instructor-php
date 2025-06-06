---
title: 'Inference and tool use'
docname: 'tool_use'
---

## Overview

`ToolUse` class automates the process of using tools by LLM, i.e.:
 - calling LLM with provided context (message sequence),
 - extracting tool calls requested by LLM from the response,
 - calling the requested tool and storing its results,
 - constructing message sequence with the result of call,
 - sending updated message sequence back to LLM.

This cycle is repeated until one of the exit criteria is met:
- LLM no longer requests any tool calls,
- specified maximum number of iterations is reached,
- specified token usage limit is reached
- there are any errors during the process (e.g. LLM requested a tool that is not available).

`ToolUse` class provides 3 ways to iterate through the process:
- manual control - code is responsible for checking `hasNextStep()` and calling `nextStep()` in a loop,
- using iterator - code uses foreach loop to iterate through the steps (internally it checks
`hasNextStep()` and calls `nextStep()`),
- just get final step - you only get the final step, iteration process is done internally.

## Example

This example demonstrates 3 ways to use `ToolUse` class to allow LLM call functions
if needed to answer simple math question. We provide 2 functions (`add_numbers` and
`subtract_numbers`) as tools available to LLM and specify the task in plain language.
The LLM is expected to call the functions in the correct order to get the final result.

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;

function add_numbers(int $a, int $b) : int {
    return $a + $b;
}

function subtract_numbers(int $a, int $b) : int {
    return $a - $b;
}

//
// PATTERN #1 - manual control
//
echo "\nPATTERN #1 - manual control\n";
$toolUse = (new ToolUse)
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...)),
    ]);

// iterate until no more steps
while ($toolUse->hasNextStep()) {
    $step = $toolUse->nextStep();
    print("STEP - tokens used: " . $step->usage()->total() . "\n");
}

// print final response
$result = $step->response();
print("RESULT: " . $result . "\n");


//
// PATTERN #2 - using iterator
//
echo "\nPATTERN #2 - using iterator\n";
$toolUse = (new ToolUse)
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...)),
    ]);

// iterate until no more steps
foreach ($toolUse->iterator() as $step) {
    print("STEP - tokens used: " . $step->usage()->total() . "\n");
}

// print final response
$result = $toolUse->context()->currentStep()->response();
print("RESULT: " . $result . "\n");



//
// PATTERN #3 - just get final step (fast forward to it)
//
echo "\nPATTERN #3 - get only final result\n";
$toolUse = (new ToolUse)
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...)),
    ]);

// print final response
$result = $toolUse->finalStep()->response();
print("RESULT: " . $result . "\n");
