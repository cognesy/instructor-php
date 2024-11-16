---
title: 'Inference and tool use'
docname: 'llm_tool_use'
---

## Overview


## Example


```php
<?php

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Enums\LLMFinishReason;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

function add_numbers($a, $b) : int {
    return $a + $b;
}

function subtract_numbers($a, $b) : int {
    return $a - $b;
}

$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'add_numbers',
            'description' => 'Add two numbers',
            'parameters' => [
                'type' => 'object',
                'description' => 'Numbers to add',
                'properties' => [
                    'a' => [
                        'type' => 'integer',
                        'description' => 'First number',
                    ],
                    'b' => [
                        'type' => 'integer',
                        'description' => 'Second number',
                    ],
                ],
                'required' => ['a', 'b'],
                'additionalProperties' => false,
            ],
        ],
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'subtract_numbers',
            'description' => 'Subtract two numbers',
            'parameters' => [
                'type' => 'object',
                'description' => 'Numbers to subtract',
                'properties' => [
                    'a' => [
                        'type' => 'integer',
                        'description' => 'First number',
                    ],
                    'b' => [
                        'type' => 'integer',
                        'description' => 'Second number',
                    ],
                ],
                'required' => ['a', 'b'],
                'additionalProperties' => false,
            ],
        ],
    ]
];

$prompt = 'Add 2455 and 3558 then subtract 4344 from the result.';

$messages = [
    ['role' => 'user', 'content' => $prompt]
];

Debug::enable();

$chat = (new Inference)
    ->withConnection('openrouter');
$toolUse = new ToolUse($chat, $tools);
$response = $toolUse
    ->withMessages($messages)
    ->response();
