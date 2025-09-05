---
title: 'Inference and ReAct tool use'
docname: 'tool_use_react'
---

## Overview

This example mirrors the ToolUse example but uses the ReActDriver which does not rely on native tool-calling capability. It extracts ReAct decisions (Thought → Action/Final) using StructuredOutput and executes tools via the ToolUse framework.

```php
<?php
require 'examples/boot.php';

use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Addons\ToolUse\ContinuationCriteria\{StepsLimit,TokenUsageLimit,ExecutionTimeLimit,RetryLimit};

function add_numbers(int $a, int $b) : int { return $a + $b; }
function subtract_numbers(int $a, int $b) : int { return $a - $b; }

// Recommended continuation criteria for ReAct: do not include ToolCallPresenceCheck.
$criteria = [
    new StepsLimit(6),
    new TokenUsageLimit(8192),
    new ExecutionTimeLimit(60),
    new RetryLimit(2),
];

// PATTERN #1 - manual control
echo "\nReAct PATTERN #1 - manual control\n";
$toolUse = (new ToolUse(continuationCriteria: $criteria))
    ->withDriver(new ReActDriver(llm: LLMProvider::using('openai')))
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...)),
    ]);

while ($toolUse->hasNextStep()) {
    $step = $toolUse->nextStep();
    print("STEP - tokens used: " . $step->usage()->total() . "\n");
}
$result = $step->response();
print("RESULT: " . $result . "\n");

// PATTERN #2 - using iterator
echo "\nReAct PATTERN #2 - using iterator\n";
$toolUse = (new ToolUse(continuationCriteria: $criteria))
    ->withDriver(new ReActDriver(llm: LLMProvider::using('openai')))
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...)),
    ]);

foreach ($toolUse->iterator() as $step) {
    print("STEP - tokens used: " . $step->usage()->total() . "\n");
}
$result = $toolUse->state()->currentStep()->response();
print("RESULT: " . $result . "\n");

// PATTERN #3 - final step only (optional finalViaInference demonstration)
echo "\nReAct PATTERN #3 - final via Inference (optional)\n";
$driver = new ReActDriver(llm: LLMProvider::using('openai'), finalViaInference: true);
$toolUse = (new ToolUse(continuationCriteria: $criteria))
    ->withDriver($driver)
    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
    ->withTools([
        FunctionTool::fromCallable(add_numbers(...)),
        FunctionTool::fromCallable(subtract_numbers(...)),
    ]);

$result = $toolUse->finalStep()->response();
print("RESULT: " . $result . "\n");
```
