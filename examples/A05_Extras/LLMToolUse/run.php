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
use Cognesy\Instructor\Features\LLM\Enums\LLMFinishReason;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Debug\Debug;
use Cognesy\Instructor\Utils\Json\Json;
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

$chat = (new Inference)
    ->withConnection('openrouter');

$prompt = 'Add 2455 and 3558 then subtract 4344 from the result.';

$messages = [
    ['role' => 'user', 'content' => $prompt]
];

Debug::enable();

$toolUse = new ToolUse($chat, $tools);
$response = $toolUse->response($messages);

class ToolUse {
    private Inference $inference;
    private array $tools;
    private int $maxDepth;
    private array $options;
    private bool $parallelToolCalls;
    private string|array $toolChoice;
    /** @var LLMResponse[] */
    private array $responses = [];

    public function __construct(
        Inference $chat,
        array $tools,
        int $maxDepth = 3,
        array $options = [],
        string|array $toolChoice = 'auto',
        bool $parallelToolCalls = false
    ) {
        $this->inference = $chat;
        $this->tools = $tools;
        $this->maxDepth = $maxDepth;
        $this->parallelToolCalls = $parallelToolCalls;
        $this->toolChoice = $toolChoice;
        $this->options = $options;
    }

    public function response(string|array $messages) : LLMResponse {
        $messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            is_array($messages) => $messages,
            default => []
        };
        $chat = (new Messages)->appendMessages($messages);
        return $this->tryGetResponse($chat);
    }

    /**
     * @return LLMResponse[]
     */
    public function responses() : array {
        return $this->responses;
    }

    // INTERNAL //////////////////////////////////////////////

    private function tryGetResponse(Messages $chat) : LLMResponse {
        $response = $this->getResponse($chat->toArray());
        $this->responses[] = $response;
        $depth = 0;
        while ($this->tryContinue($response)) {
            if ($depth++ >= $this->maxDepth) {
                break;
            }
            $responseMessages = $this->makeToolsResponseMessages($response);
            $chat->appendMessages($responseMessages);
            $response = $this->getResponse($chat->toArray());
            $this->responses[] = $response;
        }
        return $response;
    }

    private function getResponse(string|array $messages) : LLMResponse {
        return $this->inference
            ->create(
                messages: $messages,
                tools: $this->tools,
                toolChoice: $this->toolChoice,
                options: array_merge($this->options, ['parallel_tool_calls' => $this->parallelToolCalls]),
                mode: Mode::Tools,
            )->response();
    }

    private function makeToolsResponseMessages(LLMResponse $response) : array {
        $messages = [];
        $toolCalls = $response->toolCalls();
        $count = 0;
        foreach ($toolCalls->all() as $toolCall) {
            $function = $toolCall->name();
            $args = $toolCall->args();
            $result = $function(...$args);
            $resultString = match(true) {
                is_string($result) => $result,
                is_array($result) => Json::encode($result),
                is_object($result) => Json::encode($result),
                default => (string) $result,
            };
            $messages[] = [
                'role' => 'assistant',
                '_metadata' => [
                    'tool_calls' => [$toolCall->toToolCallArray()]
                ]
            ];
            $messages[] = [
                'role' => 'tool',
                'content' => $resultString,
                '_metadata' => [
                    'tool_call_id' => $toolCall->id(),
                    'tool_name' => $toolCall->name(),
                    'result' => $result
                ]
            ];
            $count++;
            if ($this->parallelToolCalls && $count >= 0) {
                break;
            }
        }
        return $messages;
    }

    private function tryContinue(LLMResponse $response) : bool {
        return $response->hasToolCalls() || LLMFinishReason::ToolCalls->equals($response->finishReason());
    }
}