<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\LLMProvider;

function add_numbers_smoke(int $a, int $b): int { return $a + $b; }

it('threads HttpClient through ToolUse -> Inference (OpenAI)', function () {
    $http = (new HttpClientBuilder())
        ->withMock(function ($mock) {
            $mock->on()
                ->post('https://api.openai.com/v1/chat/completions')
                ->withJsonSubset(['model' => 'gpt-4o-mini'])
                ->times(1)
                ->replyJson([
                    'choices' => [[
                        'message' => [
                            'content' => '',
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'add_numbers_smoke',
                                    'arguments' => '{"a":2,"b":3}',
                                ],
                            ]],
                        ],
                        'finish_reason' => 'tool_calls'
                    ]],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
                ]);
        })
        ->create();

    $driver = new ToolCallingDriver(
        llm: LLMProvider::using('openai'),
        httpClient: $http,
        model: 'gpt-4o-mini'
    );

    $toolUse = (new ToolUse)
        ->withDriver($driver)
        ->withMessages('Add two numbers and return the result')
        ->withTools([
            FunctionTool::fromCallable(add_numbers_smoke(...)),
        ]);

    $step = $toolUse->nextStep();
    expect($step->hasToolCalls())->toBeTrue();
    expect(count($step->toolExecutions()->all()))->toBe(1);
});

