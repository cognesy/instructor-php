---
title: 'Inference and tool use'
docname: 'llm_tool_use'
---

## Overview


## Example


```php
<?php

use Cognesy\Instructor\Extras\ToolUse\Tools\FunctionTool;
use Cognesy\Instructor\Extras\ToolUse\Tools\UpdateContextVariable;
use Cognesy\Instructor\Extras\ToolUse\ToolUse;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Result\Result;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

Debug::enable();

//function add_numbers(int $a, int $b) : int {
//    return $a + $b;
//}
//
//function subtract_numbers(int $a, int $b) : int {
//    return $a - $b;
//}
//
//$toolUse = (new ToolUse)
//    ->withMessages('Add 2455 and 3558 then subtract 4344 from the result.')
//    ->withTools([
//        FunctionTool::fromCallable(add_numbers(...)),
//        FunctionTool::fromCallable(subtract_numbers(...)),
//    ]);

function send_discount_code(string $name, string $email) : Result {
    return Result::success(true);
}

function save_to_crm(string $email) : Result {
    return Result::success(true);
}

$sequence = [
    ['role' => 'user', 'content' => 'Can you offer me a discount?'],
    ['role' => 'user', 'content' => 'My email is john@doe.com'],
];

$messages = Messages::fromArray([
    ['role' => 'system', 'content' => 'Your objective is to answer user questions. If user shares their email address - store it to the context variable `email` and save it to CRM. Never nag user for their email unless he asks for discount.'],
    ['role' => 'user', 'content' => 'I\'ve got your invite from nick@acme.com and I like the product, but I need assistance with your pricing'],
]);

echo $messages->toRoleString();
echo "\n";
foreach($sequence as $message) {
    $toolUse = (new ToolUse)
        ->withTools([
            new UpdateContextVariable(),
            FunctionTool::fromCallable(send_discount_code(...)),
            FunctionTool::fromCallable(save_to_crm(...)),
        ])
        ->withMessages($messages);
    $step = $toolUse->finalStep();
    //dump($step);
    $messages->appendMessage($step->response());
    echo "ASSISTANT: {$messages->last()->content()}\n";
    $messages->appendMessage($message);
    echo "USER: {$messages->last()->content()}\n";
}


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
