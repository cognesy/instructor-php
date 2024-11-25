---
title: 'Inference and tool use'
docname: 'llm_tool_use'
---

## Overview


## Example


```php
<?php

use Cognesy\Instructor\Extras\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Instructor\Extras\ToolUse\Tools\FunctionTool;
use Cognesy\Instructor\Extras\ToolUse\ToolUse;
use Cognesy\Instructor\Utils\Debug\Debug;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

function add_numbers(int $a, int $b) : int {
    return $a + $b;
}
$addTool = FunctionTool::fromCallable(add_numbers(...));

function subtract_numbers(int $a, int $b) : int {
    return $a - $b;
}
$subtractTool = FunctionTool::fromCallable(subtract_numbers(...));

//function return_result(int $result) : int {
//    dump('FINAL:', $result);
//    return $result;
//}
//$returnTool = FunctionTool::fromCallable(return_result(...));

Debug::enable();

$driver = new ToolCallingDriver();

$toolUse = (new ToolUse)
    ->withDriver($driver)
    ->withDefaultContinuationCriteria()
    ->withDefaultProcessors()
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        $addTool,
        $subtractTool,
//        $returnTool,
    ]);


# PATTERN #1 - fine grained control
// iterate until no more steps
while ($toolUse->hasNextStep()) {
    $step = $toolUse->nextStep();
}
// do something with final response
print($step->response());


//# PATTERN #2 - use iterator
//foreach ($toolUse->iterator() as $step) {
//    dump($step);
//}
//// do something with final response
//dump('final #2', $step);
//
//
//# PATTERN #3 - just get final step (fast forward to it)
//$step = $toolUse->finalStep();
//// do something with final response
//dump('final #3', $step);
